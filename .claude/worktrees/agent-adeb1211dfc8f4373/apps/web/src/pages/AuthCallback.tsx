import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { fetchCurrentUser, refreshSession } from '@/lib/api/auth';
import { useAuthStore } from '@/store/auth-store';

// Point d'atterrissage apres /auth/google/callback (voir apps/api auth.controller.ts).
// L'API a deja pose le cookie de refresh httpOnly avant la redirection : on
// recupere un access token via /auth/refresh, comme au demarrage normal de
// l'app (voir AuthProvider). Aucun token ne transite par l'URL.
export function AuthCallback() {
  const navigate = useNavigate();

  useEffect(() => {
    let cancelled = false;

    async function completeLogin() {
      try {
        const { accessToken } = await refreshSession();
        useAuthStore.getState().setAccessToken(accessToken);
        const user = await fetchCurrentUser();
        if (!cancelled) {
          useAuthStore.getState().setSession(user, accessToken);
          navigate('/');
        }
      } catch {
        if (!cancelled) {
          useAuthStore.getState().clearSession();
          navigate('/login');
        }
      }
    }

    void completeLogin();
    return () => {
      cancelled = true;
    };
  }, [navigate]);

  return (
    <main className="flex min-h-[calc(100vh-4rem)] items-center justify-center">
      <p role="status" className="font-inter text-muted-foreground">
        Connexion en cours…
      </p>
    </main>
  );
}
