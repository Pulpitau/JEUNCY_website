import type { UserRole } from '@jeuncy/shared';
import { create } from 'zustand';

export interface AuthUser {
  id: number | string;
  email: string;
  role: UserRole;
}

interface AuthState {
  user: AuthUser | null;
  /** Jeton court (15 min). En memoire uniquement, jamais dans le coffre. */
  accessToken: string | null;
  /** true tant que la restauration de session au demarrage n'est pas finie. */
  isLoading: boolean;
  setSession: (user: AuthUser, accessToken: string) => void;
  setAccessToken: (accessToken: string) => void;
  setUser: (user: AuthUser) => void;
  clearSession: () => void;
  setLoading: (isLoading: boolean) => void;
}

// Meme conception que le store du web (apps/web/src/store/auth-store.ts) :
// aucune persistance de l'access token. La difference tient au jeton long, qui
// vit dans un cookie httpOnly cote navigateur et dans le coffre du telephone
// cote mobile (voir lib/secure-store.ts).
export const useAuthStore = create<AuthState>((set) => ({
  user: null,
  accessToken: null,
  isLoading: true,
  setSession: (user, accessToken) => set({ user, accessToken, isLoading: false }),
  setAccessToken: (accessToken) => set({ accessToken }),
  setUser: (user) => set({ user }),
  clearSession: () => set({ user: null, accessToken: null, isLoading: false }),
  setLoading: (isLoading) => set({ isLoading }),
}));
