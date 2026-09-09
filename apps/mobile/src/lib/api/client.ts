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

let refreshPromise: Promise<boolean> | null = null;

// Coalesce les rafraichissements concurrents : au retour d'arriere-plan,
// plusieurs ecrans repartent en meme temps et prennent un 401 simultanement.
// Sans cette mise en commun, chacun appellerait /auth/refresh — or le serveur
// fait tourner le jeton a chaque appel, donc le deuxieme appel invaliderait le
// jeton que le premier vient d'obtenir, et la session sauterait.
function tryRefresh(): Promise<boolean> {
  refreshPromise ??= (async () => {
    const refreshToken = await readRefreshToken();
    if (!refreshToken) return false;

    const response = await rawRequest('/auth/refresh', {
      method: 'POST',
      body: { refreshToken },
      skipAuthRetry: true,
    });

    if (!response.ok) return false;

    const body = (await response.json()) as {
      data: { accessToken: string; refreshToken: string };
    };

    useAuthStore.getState().setAccessToken(body.data.accessToken);
    await writeRefreshToken(body.data.refreshToken);

    return true;
  })()
    .catch(() => false)
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
    const refreshed = await tryRefresh();
    if (refreshed) {
      response = await rawRequest(path, { ...options, skipAuthRetry: true });
    } else {
      await clearRefreshToken();
      useAuthStore.getState().clearSession();
    }
  }

  return response;
}

export async function apiRequest<T>(
  path: string,
  options: RequestOptions = {},
): Promise<T> {
  let response: Response;

  try {
    response = await requestWithRetry(path, options);
  } catch {
    // fetch ne rejette que sur un probleme reseau : hors ligne, DNS, TLS.
    // Un message explicite vaut mieux que l'echec brut de la couche native,
    // illisible pour l'utilisateur.
    throw new ApiError(
      {
        code: 'NETWORK_ERROR',
        message: 'Connexion impossible. Vérifie ta connexion internet.',
      },
      0,
    );
  }

  const body = (await response.json().catch(() => null)) as
    { success: true; data: T } | { success: false; error: ApiErrorBody } | null;

  if (!response.ok || !body || !body.success) {
    const error =
      body && !body.success
        ? body.error
        : { code: 'UNKNOWN_ERROR', message: 'Une erreur est survenue.' };
    throw new ApiError(error, response.status);
  }

  return body.data;
}
