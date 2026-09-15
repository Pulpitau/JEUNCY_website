import { useQuery, useQueryClient } from '@tanstack/react-query';

import { listMyApplications, type ApplicationWithOffer } from '@/lib/api/applications';
import { ApiError } from '@/lib/api/client';

export const MY_APPLICATIONS_KEY = ['applications', 'mine'] as const;

// Candidatures du candidat connecte, plus recentes en premier (l'API trie).
// Sans profil, l'API repond 404 PROFILE_NOT_FOUND : pour l'application c'est
// simplement « aucune candidature », pas une erreur a afficher.
export function useMyApplications(enabled = true) {
  return useQuery<ApplicationWithOffer[], ApiError>({
    queryKey: MY_APPLICATIONS_KEY,
    queryFn: async () => {
      try {
        return await listMyApplications();
      } catch (error) {
        if (error instanceof ApiError && error.status === 404) return [];
        throw error;
      }
    },
    enabled,
  });
}

export function useInvalidateApplications() {
  const queryClient = useQueryClient();

  return () => queryClient.invalidateQueries({ queryKey: MY_APPLICATIONS_KEY });
}
