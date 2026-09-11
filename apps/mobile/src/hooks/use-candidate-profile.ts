import { useQuery, useQueryClient } from '@tanstack/react-query';

import { getMyProfile, type CandidateProfile } from '@/lib/api/candidate-profile';
import { ApiError } from '@/lib/api/client';

export const CANDIDATE_PROFILE_KEY = ['candidate-profile'] as const;

// Le profil du candidat connecte. Un compte fraichement cree n'en a pas :
// l'API repond alors 404 PROFILE_NOT_FOUND. Ce n'est pas une
// erreur pour l'application, c'est l'etat « a creer » — la requete le
// transforme en `null` plutot que de laisser TanStack Query le traiter comme
// un echec (et le reessayer pour rien).
export function useCandidateProfile() {
  return useQuery<CandidateProfile | null, ApiError>({
    queryKey: CANDIDATE_PROFILE_KEY,
    queryFn: async () => {
      try {
        return await getMyProfile();
      } catch (error) {
        if (error instanceof ApiError && error.status === 404) return null;
        throw error;
      }
    },
  });
}

// A appeler apres toute mutation du profil : l'ecran resume se recharge, et
// toutes les sous-listes avec lui puisque l'API renvoie le profil complet.
export function useInvalidateProfile() {
  const queryClient = useQueryClient();

  return () => queryClient.invalidateQueries({ queryKey: CANDIDATE_PROFILE_KEY });
}
