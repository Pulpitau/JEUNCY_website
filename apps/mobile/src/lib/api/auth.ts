import type { UserRole } from '@jeuncy/shared';

import { useAuthStore, type AuthUser } from '@/store/auth-store';

import { clearRefreshToken, readRefreshToken, writeRefreshToken } from '../secure-store';
import { ApiError, apiRequest, isSessionRejection } from './client';

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
      // age_confirmed : la case « J'ai 16 ans ou plus » cochee a l'inscription.
      // Le formulaire ne laisse pas soumettre sans, donc toujours true ici ;
      // le serveur la valide s'il la recoit (RegisterRequest, 2026-09-11).
      body: { email, password, role, age_confirmed: true },
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
  } catch {
    // Hors ligne ou jeton deja expire : la deconnexion locale a lieu quand
    // meme (ci-dessous). Refuser de deconnecter quelqu'un parce que le reseau
    // est absent serait absurde, et l'echec n'a rien a montrer a l'ecran.
  } finally {
    await clearRefreshToken();
    useAuthStore.getState().clearSession();
  }
}

/**
 * Issue de la restauration au demarrage :
 * - `restored` : session ouverte ;
 * - `signed-out` : aucune session, ou le serveur l'a refusee (jeton expire,
 *   revoque, compte suspendu) — on passe par l'ecran de connexion ;
 * - `offline` : le serveur n'a pas pu repondre (reseau, 5xx, 429). Le jeton
 *   est GARDE : l'ecran propose de reessayer au lieu de faire croire a une
 *   session perdue.
 */
export type RestoreOutcome = 'restored' | 'signed-out' | 'offline';

// Restauration de session au demarrage : le refresh token dort dans le coffre,
// on l'echange contre un access token frais avant d'afficher quoi que ce soit.
// C'est ce qui fait qu'on reste connecte apres avoir ferme l'application.
export async function restoreSession(): Promise<RestoreOutcome> {
  const store = useAuthStore.getState();
  const refreshToken = await readRefreshToken();

  if (!refreshToken) {
    store.clearSession();

    return 'signed-out';
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

    return 'restored';
  } catch (error) {
    if (error instanceof ApiError && isSessionRejection(error.status)) {
      // Jeton expire, revoque (deconnexion depuis un autre appareil,
      // changement de mot de passe) ou compte suspendu : session vierge.
      await clearRefreshToken();
      store.clearSession();

      return 'signed-out';
    }

    // Panne : le jeton reste dans le coffre, rien n'est efface.
    store.setLoading(false);

    return 'offline';
  }
}
