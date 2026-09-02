import { useAuthStore } from '@/store/auth-store';

export const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:3000';

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
  // Empeche la tentative de refresh automatique (utilise en interne pour
  // eviter une boucle infinie sur /auth/refresh lui-meme).
  skipAuthRetry?: boolean;
}

async function rawRequest(path: string, options: RequestOptions = {}): Promise<Response> {
  const { accessToken } = useAuthStore.getState();
  const isFormData = options.body instanceof FormData;
  const headers = new Headers(options.headers);
  if (options.body !== undefined && !isFormData) {
    // Pour FormData, ne jamais fixer Content-Type nous-memes : le navigateur doit
    // generer la boundary multipart lui-meme, sinon la requete est mal formee.
    headers.set('Content-Type', 'application/json');
  }
  if (accessToken) {
    headers.set('Authorization', `Bearer ${accessToken}`);
  }

  return fetch(`${API_URL}${path}`, {
    ...options,
    headers,
    credentials: 'include',
    body: isFormData
      ? (options.body as FormData)
      : options.body !== undefined
        ? JSON.stringify(options.body)
        : undefined,
  });
}

let refreshPromise: Promise<boolean> | null = null;

// Coalesce les refresh concurrents (plusieurs requetes en 401 en meme temps
// ne declenchent qu'un seul appel a /auth/refresh).
function tryRefresh(): Promise<boolean> {
  refreshPromise ??= rawRequest('/auth/refresh', { method: 'POST', skipAuthRetry: true })
    .then(async (response) => {
      if (!response.ok) return false;
      const body = (await response.json()) as { data: { accessToken: string } };
      useAuthStore.getState().setAccessToken(body.data.accessToken);
      return true;
    })
    .catch(() => false)
    .finally(() => {
      refreshPromise = null;
    });

  return refreshPromise;
}

export async function apiRequest<T>(
  path: string,
  options: RequestOptions = {},
): Promise<T> {
  let response = await rawRequest(path, options);

  if (response.status === 401 && !options.skipAuthRetry) {
    const refreshed = await tryRefresh();
    if (refreshed) {
      response = await rawRequest(path, { ...options, skipAuthRetry: true });
    } else {
      useAuthStore.getState().clearSession();
    }
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

// Variante binaire d'apiRequest : meme authentification et meme retry sur 401,
// mais la reponse attendue est un fichier et non du JSON enveloppe.
// Necessaire pour le telechargement de CV depuis la CVtheque, que le serveur
// sert en flux PDF plutot qu'en URL — l'URL du fichier ne doit jamais
// circuler cote client (elle contournerait la garde d'abonnement et le
// journal de telechargement).
export async function apiDownload(
  path: string,
  options: RequestOptions = {},
): Promise<{ blob: Blob; filename: string | null }> {
  let response = await rawRequest(path, options);

  if (response.status === 401 && !options.skipAuthRetry) {
    const refreshed = await tryRefresh();
    if (refreshed) {
      response = await rawRequest(path, { ...options, skipAuthRetry: true });
    } else {
      useAuthStore.getState().clearSession();
    }
  }

  if (!response.ok) {
    // Le serveur repond en JSON quand il refuse (402 abonnement requis, 404
    // profil retire de la CVtheque) : on recupere le vrai code d'erreur pour
    // que l'appelant affiche le bon message plutot qu'un echec generique.
    const body = (await response.json().catch(() => null)) as {
      success: false;
      error: ApiErrorBody;
    } | null;

    throw new ApiError(
      body?.error ?? { code: 'UNKNOWN_ERROR', message: 'Une erreur est survenue.' },
      response.status,
    );
  }

  return {
    blob: await response.blob(),
    filename: parseFilename(response.headers.get('Content-Disposition')),
  };
}

// Extrait le nom de fichier de l'en-tete Content-Disposition. Renvoie null si
// l'en-tete est absent ou illisible : l'appelant fournit alors son propre nom
// de repli plutot que d'enregistrer un fichier sans nom.
function parseFilename(header: string | null): string | null {
  if (!header) return null;
  const match = /filename="?([^";]+)"?/i.exec(header);
  return match ? match[1].trim() : null;
}
