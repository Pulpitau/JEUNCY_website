import { useEffect, type ReactNode } from 'react';
import { useAuthStore } from '@/store/auth-store';
import { fetchCurrentUser, refreshSession } from '@/lib/api/auth';

interface AuthProviderProps {
  children: ReactNode;
}

export function AuthProvider({ children }: AuthProviderProps) {
  useEffect(() => {
    let cancelled = false;

    async function bootstrap() {
      try {
        const { accessToken } = await refreshSession();
        useAuthStore.getState().setAccessToken(accessToken);
        const user = await fetchCurrentUser();
        if (!cancelled) {
          useAuthStore.getState().setSession(user, accessToken);
        }
      } catch {
        // Pas de session active (pas de cookie, ou expiree) : etat non connecte.
        if (!cancelled) {
          useAuthStore.getState().clearSession();
        }
      }
    }

    void bootstrap();
    return () => {
      cancelled = true;
    };
  }, []);

  return children;
}
