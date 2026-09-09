import type { UserRole } from '@jeuncy/shared';

import { useAuthStore, type AuthUser } from '@/store/auth-store';

import { clearRefreshToken, readRefreshToken, writeRefreshToken } from '../secure-store';
import { ApiError, apiRequest } from './client';

// Reponse des routes qui ouvrent une session. Le champ refreshToken n'existe
// que parce que l'application se declare mobile (en-tete X-Jeuncy-Client) : un
// navigateur, lui, ne le recoit jamais dans le corps.
interface SessionResponse {
  user: AuthUser;
  accessToken: string;
  refreshToken: string;
}

async function openSession(response: SessionResponse): Promise<AuthUser> {
  // Sans refresh token, la session durerait 15 minutes puis sauterait sans
  // explication. Le cas a une cause unique et connue : une version du serveur
  // anterieure au mode mobile de AuthController. Le dire franchement ici evite
  // de chercher un bug dans l'application alors que le probleme est un fichier
  // qui n'est pas arrive a destination.
  if (!response.refreshToken) {
    throw new ApiError(
      {
        code: 'MOBILE_MODE_UNSUPPORTED',
        message:
          "Le serveur n'a pas renvoyé de jeton de session. Vérifie que la version mobile de l'API est bien déployée.",
      },
      500,
    );
  }

  await writeRefreshToken(response.refreshToken);
  useAuthStore.getState().setSession(response.user, response.accessToken);

  return response.user;
}

export async function login(email: string, password: string): Promise<AuthUser> {
  return openSession(
    await apiRequest<SessionResponse>('/auth/login', {
      method: 'POST',
      body: { email, password },
    }),
  );
}

export async function register(
  email: string,
  password: string,
  role: UserRole,
): Promise<AuthUser> {
  return openSession(
    await apiRequest<SessionResponse>('/auth/register', {
      method: 'POST',
      body: { email, password, role },
    }),
  );
}

export async function forgotPassword(email: string): Promise<void> {
  await apiRequest<{ message: string }>('/auth/forgot-password', {
    method: 'POST',
    body: { email },
  });
}

export async function fetchMe(): Promise<AuthUser> {
  return apiRequest<AuthUser>('/auth/me');
}

export async function logout(): Promise<void> {
  try {
    await apiRequest<{ loggedOut: boolean }>('/auth/logout', { method: 'POST' });
  } finally {
    // Le nettoyage local a lieu meme si l'appel echoue (hors ligne, jeton deja
    // expire) : refuser de deconnecter quelqu'un parce que le reseau est
    // absent serait absurde.
    await clearRefreshToken();
    useAuthStore.getState().clearSession();
  }
}

// Restauration de session au demarrage : le refresh token dort dans le coffre,
// on l'echange contre un access token frais avant d'afficher quoi que ce soit.
// C'est ce qui fait qu'on reste connecte apres avoir ferme l'application.
export async function restoreSession(): Promise<void> {
  const store = useAuthStore.getState();
  const refreshToken = await readRefreshToken();

  if (!refreshToken) {
    store.clearSession();

    return;
  }

  try {
    const tokens = await apiRequest<{ accessToken: string; refreshToken: string }>(
      '/auth/refresh',
      { method: 'POST', body: { refreshToken }, skipAuthRetry: true },
    );

    await writeRefreshToken(tokens.refreshToken);
    store.setAccessToken(tokens.accessToken);

    // Le refresh ne renvoie que des jetons : il faut une seconde requete pour
    // savoir QUI est connecte (et avec quel role, ce qui commande toute la
    // navigation).
    store.setSession(await fetchMe(), tokens.accessToken);
  } catch {
    // Jeton expire, revoque (deconnexion depuis un autre appareil, changement
    // de mot de passe) ou compte suspendu : on repart d'une session vierge.
    await clearRefreshToken();
    store.clearSession();
  }
}
