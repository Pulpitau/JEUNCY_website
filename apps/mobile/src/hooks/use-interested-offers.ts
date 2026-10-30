import { useQuery } from '@tanstack/react-query';

import { DISCOVER_KEY } from '@/hooks/use-discover-deck';
import { ApiError } from '@/lib/api/client';
import { discoverOffers, type DeckJeuncyOffer } from '@/lib/api/discover';

// « Ils s'intéressent à toi » : les offres Jeuncy dont l'employeur a dit oui
// en premier. Même source que la page web /interesses (InterestedInMe.tsx) :
// la Sélection du jour de `discover/offers`, filtrée sur
// `employer_interested`. Le serveur en retire déjà ce que le candidat a jugé.
//
// Clé sous DISCOVER_KEY : toute invalidation de la pile (geste, rayon,
// profil) rafraîchit aussi cette liste. Clé distincte quand même, parce que
// la pile garde ses propres données à côté.
export const INTERESTED_OFFERS_KEY = [...DISCOVER_KEY, 'interested'] as const;

export function useInterestedOffers(enabled = true) {
  return useQuery<DeckJeuncyOffer[], ApiError>({
    queryKey: INTERESTED_OFFERS_KEY,
    queryFn: async () => {
      const { jeuncy } = await discoverOffers(1);

      return jeuncy.filter((offer) => offer.employer_interested);
    },
    enabled,
    // Les refus de la garde du match (profil, âge) sont définitifs pour cet
    // écran : les rejouer ferait attendre pour rien.
    retry: false,
  });
}
