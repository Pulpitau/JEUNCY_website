import { useAuthStore } from '@/store/auth-store';

const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:3000';

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
  const headers = new Headers(options.headers);
  if (options.body !== undefined) {
    headers.set('Content-Type', 'application/json');
  }
  if (accessToken) {
    headers.set('Authorization', `Bearer ${accessToken}`);
  }

  return fetch(`${API_URL}${path}`, {
    ...options,
    headers,
    credentials: 'include',
    body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
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
