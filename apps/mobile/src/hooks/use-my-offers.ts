import { useQuery, useQueryClient } from '@tanstack/react-query';

import { useOrganizationRole } from '@/hooks/use-organization';
import { canDiscoverFrom, listMyOffers, type JobOffer } from '@/lib/api/job-offers';

// Les offres de l'entreprise ou du CFA connecte. Elles servent a deux choses
// tres differentes : la gestion (« Mes offres ») et le choix de la pile
// (« Decouvrir »), d'ou le second selecteur.

export const MY_OFFERS_KEY = ['job-offers', 'mine'] as const;

export function useMyOffers() {
  const role = useOrganizationRole();

  return useQuery({
    queryKey: MY_OFFERS_KEY,
    queryFn: listMyOffers,
    enabled: role !== null,
  });
}

/**
 * Les offres qui peuvent porter une pile : publiees ET localisees.
 *
 * Le serveur refuse les autres nommement (JOB_OFFER_NOT_PUBLISHED,
 * JOB_OFFER_NOT_LOCATED). Les ecarter ici evite de faire choisir a
 * l'employeur une offre dont on sait deja qu'elle repondra une erreur — mais
 * l'ecran doit quand meme savoir qu'elles existent, sinon une entreprise
 * avec trois brouillons croirait n'avoir aucune offre.
 */
export function useDiscoverableOffers() {
  const query = useMyOffers();
  const offers = query.data ?? [];

  const discoverable = offers.filter(canDiscoverFrom);
  const blocked = offers.filter((offer) => !canDiscoverFrom(offer));

  return { ...query, offers, discoverable, blocked };
}

/** Ce qui manque a une offre pour entrer dans Decouvrir, en une phrase. */
export function whyNotDiscoverable(offer: JobOffer): string {
  if (offer.status !== 'PUBLISHED') return 'À publier';
  if (offer.postal_code === null) return 'Code postal manquant';

  return '';
}

export function useInvalidateMyOffers() {
  const queryClient = useQueryClient();

  return () => queryClient.invalidateQueries({ queryKey: MY_OFFERS_KEY });
}
