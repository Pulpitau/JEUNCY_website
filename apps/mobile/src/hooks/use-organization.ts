import { useQuery, useQueryClient } from '@tanstack/react-query';

import { ApiError } from '@/lib/api/client';
import {
  getMyOrganization,
  isOrganizationRole,
  type Organization,
  type OrganizationRole,
} from '@/lib/api/organization';
import { useAuthStore } from '@/store/auth-store';

export const ORGANIZATION_KEY = ['organization'] as const;

// Le role de l'utilisateur connecte, s'il est entreprise ou CFA ; null sinon.
export function useOrganizationRole(): OrganizationRole | null {
  const role = useAuthStore((state) => state.user?.role);

  return isOrganizationRole(role) ? role : null;
}

// Fiche de l'entreprise ou du CFA connecte. Meme convention que le profil
// candidat : un compte sans fiche recoit 404 (COMPANY_NOT_FOUND /
// CFA_ORGANIZATION_NOT_FOUND), traduit en `null` — c'est l'etat « a creer ».
export function useOrganization() {
  const role = useOrganizationRole();

  return useQuery<Organization | null, ApiError>({
    queryKey: ORGANIZATION_KEY,
    queryFn: async () => {
      if (!role) return null;
      try {
        return await getMyOrganization(role);
      } catch (error) {
        if (error instanceof ApiError && error.status === 404) return null;
        throw error;
      }
    },
    enabled: role !== null,
  });
}

export function useInvalidateOrganization() {
  const queryClient = useQueryClient();

  return () => queryClient.invalidateQueries({ queryKey: ORGANIZATION_KEY });
}
