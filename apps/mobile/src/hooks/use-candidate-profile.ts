import { useQuery, useQueryClient } from '@tanstack/react-query';

import { DISCOVER_KEY } from '@/hooks/use-discover-deck';
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

/**
 * Profil ET pile Decouvrir. A utiliser des qu'un enregistrement touche ce
 * que la pile lit : existence du profil, date de naissance, commune et code
 * postal (position), rayon, contrats, secteurs. Sans cela, l'onglet Decouvrir
 * — qui reste monte — garde sa reponse d'avant, y compris l'ecran « Cree ton
 * profil » d'un candidat qui vient de le creer.
 */
export function useInvalidateProfileAndDeck() {
  const queryClient = useQueryClient();

  // La pile se recharge en arriere-plan : l'ecran n'attend que le profil.
  return () => {
    void queryClient.invalidateQueries({ queryKey: DISCOVER_KEY });

    return queryClient.invalidateQueries({ queryKey: CANDIDATE_PROFILE_KEY });
  };
}
