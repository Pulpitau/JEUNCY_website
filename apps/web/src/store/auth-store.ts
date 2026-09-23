import { create } from 'zustand';
import type { UserRole } from '@jeuncy/shared';

import { clearAllProfileDrafts } from '@/lib/profile-draft';

export interface AuthUser {
  id: string;
  email: string;
  role: UserRole;
}

interface AuthState {
  user: AuthUser | null;
  accessToken: string | null;
  // true tant que la restauration de session (refresh silencieux au chargement)
  // n'est pas terminee — evite un flash "non connecte" au demarrage.
  isLoading: boolean;
  setSession: (user: AuthUser, accessToken: string) => void;
  setAccessToken: (accessToken: string) => void;
  clearSession: () => void;
  setLoading: (isLoading: boolean) => void;
}

// Pas de persistance localStorage : le refresh token vit uniquement dans un
// cookie httpOnly, la session est restauree via /auth/refresh au chargement
// (voir AuthProvider).
export const useAuthStore = create<AuthState>((set) => ({
  user: null,
  accessToken: null,
  isLoading: true,
  setSession: (user, accessToken) => set({ user, accessToken, isLoading: false }),
  setAccessToken: (accessToken) => set({ accessToken }),
  // Le brouillon de profil part avec la session, ici et pas dans chaque
  // appelant : clearSession est declenche depuis cinq endroits (deconnexion,
  // refresh expire, suppression de compte, callback OAuth), et un seul oubli
  // laisserait le nom et le telephone d'un candidat visibles au suivant sur
  // un poste partage.
  clearSession: () => {
    clearAllProfileDrafts();
    set({ user: null, accessToken: null, isLoading: false });
  },
  setLoading: (isLoading) => set({ isLoading }),
}));
