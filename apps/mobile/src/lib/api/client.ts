import { useAuthStore } from '@/store/auth-store';

import { clearRefreshToken, readRefreshToken, writeRefreshToken } from '../secure-store';

// Adresse de l'API. Surchargeable par EXPO_PUBLIC_API_URL dans apps/mobile/.env
// (par exemple pour viser un Laravel local), sinon la production.
//
// Le suffixe /api est indispensable : Laravel prefixe automatiquement toutes
// les routes de routes/api.php. L'oublier est precisement le bug qui a casse
// silencieusement le frontend web en phase 2 (voir CLAUDE.md).
export const API_URL = process.env.EXPO_PUBLIC_API_URL ?? 'https://api.jeuncy.com/api';

// En-tete par lequel l'application se declare aupres de l'API. Il commande le
// mode mobile de l'authentification cote serveur : le refresh token voyage
// alors dans le corps JSON, et /auth/refresh ignore tout cookie
// (AuthController::isMobileClient, teste par MobileAuthTest).
const CLIENT_HEADER = { 'X-Jeuncy-Client': 'mobile' } as const;

interface ApiErrorBody {
  code: string;
  message: string;
}

export class ApiError extends Error {
  code: string;
  status: number;

  constructor(body: ApiErrorBody, status: number) {
    super(body.message);
    this.name = 'ApiError';
    this.code = body.code;
    this.status = status;
  }
}

interface RequestOptions extends Omit<RequestInit, 'body'> {
  body?: unknown;
  /** Empeche le rejeu automatique apres un 401 (evite une boucle sur /auth/refresh). */
  skipAuthRetry?: boolean;
}

async function rawRequest(path: string, options: RequestOptions = {}): Promise<Response> {
  const { accessToken } = useAuthStore.getState();
  const isFormData = options.body instanceof FormData;
  const headers = new Headers(options.headers);
  headers.set('Accept', 'application/json');
  headers.set('X-Jeuncy-Client', CLIENT_HEADER['X-Jeuncy-Client']);

  if (options.body !== undefined && !isFormData) {
    // Pour FormData, ne jamais fixer Content-Type soi-meme : la couche reseau
    // doit generer la boundary multipart, sinon la requete est mal formee.
    headers.set('Content-Type', 'application/json');
  }
  if (accessToken) {
    headers.set('Authorization', `Bearer ${accessToken}`);
  }

  // Pas de `credentials: 'include'` ici, contrairement au web : il n'y a aucun
  // cookie a joindre, la session tient entierement au couple
  // access token en memoire + refresh token dans le coffre.
  return fetch(`${API_URL}${path}`, {
    ...options,
    headers,
    body: isFormData
      ? (options.body as FormData)
      : options.body !== undefined
        ? JSON.stringify(options.body)
        : undefined,
  });
}

// Issue d'un rafraichissement. Trois cas, pas deux : un serveur qui REFUSE le
// jeton (400, 401, 403 : expire, revoque, compte suspendu) n'a rien a voir
// avec un serveur INJOIGNABLE (hors ligne, 5xx, 429). Seul le premier met fin
// a la session ; effacer le jeton dans le second deconnecterait pour de bon
// quelqu'un qui a simplement traverse un tunnel.
type RefreshOutcome = 'refreshed' | 'rejected' | 'unavailable';

/** Statuts par lesquels le serveur refuse une session (et non une panne). */
export function isSessionRejection(status: number): boolean {
  return status === 400 || status === 401 || status === 403;
}

let refreshPromise: Promise<RefreshOutcome> | null = null;

// Coalesce les rafraichissements concurrents : au retour d'arriere-plan,
// plusieurs ecrans repartent en meme temps et prennent un 401 simultanement.
// Sans cette mise en commun, chacun appellerait /auth/refresh — or le serveur
// fait tourner le jeton a chaque appel, donc le deuxieme appel invaliderait le
// jeton que le premier vient d'obtenir, et la session sauterait.
function tryRefresh(): Promise<RefreshOutcome> {
  refreshPromise ??= (async (): Promise<RefreshOutcome> => {
    const refreshToken = await readRefreshToken();
    if (!refreshToken) return 'rejected';

    const response = await rawRequest('/auth/refresh', {
      method: 'POST',
      body: { refreshToken },
      skipAuthRetry: true,
    });

    if (!response.ok) {
      return isSessionRejection(response.status) ? 'rejected' : 'unavailable';
    }

    const body = (await response.json()) as {
      data: { accessToken: string; refreshToken: string };
    };

    useAuthStore.getState().setAccessToken(body.data.accessToken);
    await writeRefreshToken(body.data.refreshToken);

    return 'refreshed';
  })()
    .catch((): RefreshOutcome => 'unavailable')
    .finally(() => {
      refreshPromise = null;
    });

  return refreshPromise;
}

async function requestWithRetry(
  path: string,
  options: RequestOptions,
): Promise<Response> {
  let response = await rawRequest(path, options);

  if (response.status === 401 && !options.skipAuthRetry) {
    const outcome = await tryRefresh();
    if (outcome === 'refreshed') {
      response = await rawRequest(path, { ...options, skipAuthRetry: true });
    } else if (outcome === 'rejected') {
      await clearRefreshToken();
      useAuthStore.getState().clearSession();
    } else {
      // Serveur injoignable : la session reste intacte, le prochain essai
      // la rafraichira. On le dit plutot que d'afficher un 401 trompeur.
      throw new ApiError(
        {
          code: 'SESSION_UNAVAILABLE',
          message: 'Connexion au serveur impossible. Réessaie dans un instant.',
        },
        0,
      );
    }
  }

  return response;
}

// Messages par defaut de Laravel (« Too Many Attempts. », « Server Error »,
// « Unauthenticated. », « No query results for model … »),
// renvoyes tels quels sous ces codes generiques par bootstrap/app.php.
// Aucune ApiException n'utilise ces codes : leur message n'est donc jamais
// un texte ecrit par Jeuncy, et on peut le remplacer sans rien perdre.
const GENERIC_MESSAGES: Record<string, string> = {
  TOO_MANY_REQUESTS: 'Trop de tentatives. Patiente quelques minutes avant de réessayer.',
  INTERNAL_ERROR: 'Le serveur a rencontré un problème. Réessaie dans un instant.',
  UNAUTHORIZED: 'Ta session a expiré. Reconnecte-toi.',
  NOT_FOUND: "Cet élément n'existe plus ou a été retiré.",
};

const NETWORK_ERROR_BODY: ApiErrorBody = {
  code: 'NETWORK_ERROR',
  message: 'Connexion impossible. Vérifie ta connexion internet.',
};

function toApiError(body: ApiErrorBody | null | undefined, status: number): ApiError {
  const error = body ?? { code: 'UNKNOWN_ERROR', message: 'Une erreur est survenue.' };
  const message = GENERIC_MESSAGES[error.code] ?? error.message;

  return new ApiError({ code: error.code, message }, status);
}

// fetch ne rejette que sur un probleme reseau : hors ligne, DNS, TLS.
// Un message explicite vaut mieux que l'echec brut de la couche native,
// illisible pour l'utilisateur — mais cet echec brut est conserve dans
// `cause` : c'est la seule trace de ce qui s'est reellement passe, et sans
// elle un upload qui echoue est indistinguable d'un Wi-Fi coupe.
function networkError(cause: unknown): ApiError {
  if (cause instanceof ApiError) return cause;
  const error = new ApiError(NETWORK_ERROR_BODY, 0);
  error.cause = cause;

  return error;
}

export async function apiRequest<T>(
  path: string,
  options: RequestOptions = {},
): Promise<T> {
  let response: Response;

  try {
    response = await requestWithRetry(path, options);
  } catch (cause) {
    throw networkError(cause);
  }

  const body = (await response.json().catch(() => null)) as
    { success: true; data: T } | { success: false; error: ApiErrorBody } | null;

  if (!response.ok || !body || !body.success) {
    throw toApiError(body && !body.success ? body.error : null, response.status);
  }

  return body.data;
}

// Variante binaire d'apiRequest, alignee sur apps/web : meme authentification
// et meme rejeu sur 401, mais la reponse est un fichier. Sert le CV d'un
// dossier, que le serveur envoie en flux plutot qu'en adresse — une adresse
// de fichier marche sans compte et pour toujours.
export async function apiDownload(
  path: string,
): Promise<{ bytes: Uint8Array; filename: string | null }> {
  let response: Response;
  try {
    response = await requestWithRetry(path, {});
  } catch (cause) {
    throw networkError(cause);
  }

  if (!response.ok) {
    const body = (await response.json().catch(() => null)) as {
      error?: ApiErrorBody;
    } | null;
    throw toApiError(body?.error, response.status);
  }

  const disposition = response.headers.get('Content-Disposition');
  const match = disposition ? /filename="?([^";]+)"?/i.exec(disposition) : null;

  return {
    bytes: new Uint8Array(await response.arrayBuffer()),
    filename: match ? match[1].trim() : null,
  };
}
