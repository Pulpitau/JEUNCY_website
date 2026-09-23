import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Alert } from 'react-native';

import { DISCOVER_KEY } from '@/hooks/use-discover-deck';
import { ApiError } from '@/lib/api/client';
import {
  decideExternalOffer,
  listKeptOffers,
  markExternalDone,
} from '@/lib/api/external-interests';

// Les offres partenaires gardées par le candidat.
//
// Elles vivent à part des candidatures Jeuncy, et l'écran doit le dire : une
// offre gardée n'est pas une candidature envoyée. Jeuncy n'a aucun moyen de
// savoir si le candidat a postulé sur le site de l'employeur — « C'est
// fait » est une note qu'il se laisse à lui-même, pas un suivi.

export const KEPT_OFFERS_KEY = ['external-interests', 'kept'] as const;

export function useKeptOffers(enabled = true) {
  return useQuery({
    queryKey: KEPT_OFFERS_KEY,
    queryFn: async () => {
      try {
        return await listKeptOffers();
      } catch (error) {
        // Un compte sans profil candidat, ou de moins de 16 ans, se voit
        // refuser la route par la garde du match. Pour cet écran, c'est
        // « aucune offre gardée », pas une erreur à afficher au milieu des
        // candidatures.
        if (error instanceof ApiError && error.status === 403) return [];
        throw error;
      }
    },
    enabled,
  });
}

export function useMarkKeptDone() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: markExternalDone,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: KEPT_OFFERS_KEY }),
  });
}

/** « Je garde » depuis la fiche d'une offre partenaire, hors de la pile. */
export function useKeepOffer() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (externalJobOfferId: number) =>
      decideExternalOffer(externalJobOfferId, 'KEEP'),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: KEPT_OFFERS_KEY });
      // L'offre gardée sort de la pile : le serveur l'exclut désormais.
      void queryClient.invalidateQueries({ queryKey: DISCOVER_KEY });
    },
    onError: (error: Error) => Alert.alert('Offre non gardée', error.message),
  });
}
