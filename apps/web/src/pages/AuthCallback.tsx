import { useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { fetchCurrentUser } from '@/lib/api/auth';
import { useAuthStore } from '@/store/auth-store';

// Point d'atterrissage apres /auth/google/callback (voir apps/api auth.controller.ts).
export function AuthCallback() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();

  useEffect(() => {
    const accessToken = searchParams.get('accessToken');
    if (!accessToken) {
      navigate('/login');
      return;
    }

    useAuthStore.getState().setAccessToken(accessToken);
    fetchCurrentUser()
      .then((user) => {
        useAuthStore.getState().setSession(user, accessToken);
        navigate('/');
      })
      .catch(() => {
        useAuthStore.getState().clearSession();
        navigate('/login');
      });
  }, [navigate, searchParams]);

  return (
    <main className="flex min-h-[calc(100vh-4rem)] items-center justify-center">
      <p role="status" className="font-inter text-muted-foreground">
        Connexion en cours…
      </p>
    </main>
  );
}
