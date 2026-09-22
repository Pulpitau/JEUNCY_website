import { useInfiniteQuery } from '@tanstack/react-query';
import { useEffect, useMemo } from 'react';

import { searchExternalOffers, type ExternalJobOffer } from '@/lib/api/external-offers';
import { searchPublicOffers, type PublicJobOffer } from '@/lib/api/job-offers';
import { swipeKeyFor, useSwipeStore, type SwipeKey } from '@/store/swipe-store';

// Construit la pile de « Decouvrir » : les offres Jeuncy publiees d'abord
// (toutes, ou presque — voir MAX_JEUNCY_PAGES), puis les offres partenaires
// du departement choisi, page par page. Les cartes deja jugees (PASS,
// INTEREST, KEEP) sont retirees ; la page suivante part quand il reste moins
// de RELOAD_BELOW cartes, pour que le candidat ne voie jamais le fond.
//
// Prototype : aucune route `discover/offers` n'existe encore, on reutilise
// les deux recherches publiques. Le tri par distance et la selection du jour
// (lot fini de 20) viendront avec le serveur.

export type DeckCard =
  | { kind: 'jeuncy'; key: SwipeKey; offer: PublicJobOffer }
  | { kind: 'lba'; key: SwipeKey; offer: ExternalJobOffer };

export type DeckStatus = 'loading' | 'error' | 'empty' | 'ready';

/** En dessous de ce nombre de cartes restantes, on charge la page suivante. */
const RELOAD_BELOW = 5;
/**
 * Plafond de pages Jeuncy (12 offres par page). Aujourd'hui il y a moins
 * d'une page ; le plafond evite seulement de vider toute la base le jour ou
 * il y en aura des centaines.
 */
const MAX_JEUNCY_PAGES = 5;

export const DISCOVER_KEY = ['discover'] as const;

export function useDiscoverDeck(department: string) {
  const gestures = useSwipeStore((state) => state.gestures);
  const hydrated = useSwipeStore((state) => state.hydrated);

  const jeuncy = useInfiniteQuery({
    queryKey: [...DISCOVER_KEY, 'jeuncy'],
    queryFn: ({ pageParam }) => searchPublicOffers({ page: pageParam }),
    initialPageParam: 1,
    getNextPageParam: (last) =>
      last.current_page < Math.min(last.last_page, MAX_JEUNCY_PAGES)
        ? last.current_page + 1
        : undefined,
  });

  const lba = useInfiniteQuery({
    queryKey: [...DISCOVER_KEY, 'lba', department],
    queryFn: ({ pageParam }) => searchExternalOffers({ department, page: pageParam }),
    initialPageParam: 1,
    getNextPageParam: (last) =>
      last.current_page < last.last_page ? last.current_page + 1 : undefined,
    // Les partenaires attendent la reponse Jeuncy (succes ou echec) : sinon
    // leurs cartes s'afficheraient d'abord, puis les offres Jeuncy viendraient
    // s'inserer en tete sous le doigt du candidat. Et ils attendent le store :
    // avant hydratation, `department` vaut le defaut, pas celui du candidat,
    // et la requete partirait pour rien.
    enabled: hydrated && !jeuncy.isPending,
  });

  const jeuncyPages = jeuncy.data?.pages;
  const lbaPages = lba.data?.pages;

  // Toutes les cartes chargees, dans l'ordre de la pile. Une meme offre peut
  // revenir sur deux pages si l'API en a publie une nouvelle entre temps :
  // dedoublonnee par cle, sinon React se plaindrait de deux cles identiques.
  const cards = useMemo(() => {
    const seen = new Set<string>();
    const result: DeckCard[] = [];
    const add = (card: DeckCard) => {
      if (seen.has(card.key)) return;
      seen.add(card.key);
      result.push(card);
    };

    for (const page of jeuncyPages ?? []) {
      for (const offer of page.data) {
        add({ kind: 'jeuncy', key: swipeKeyFor('jeuncy', offer.id), offer });
      }
    }
    for (const page of lbaPages ?? []) {
      for (const offer of page.data) {
        add({ kind: 'lba', key: swipeKeyFor('lba', offer.id), offer });
      }
    }

    return result;
  }, [jeuncyPages, lbaPages]);

  const deck = useMemo(
    () => (hydrated ? cards.filter((card) => !(card.key in gestures)) : []),
    [cards, gestures, hydrated],
  );

  // Rechargement anticipe : Jeuncy d'abord (l'ordre de la pile en depend),
  // les partenaires ensuite. Pas de setState ici, seulement des requetes.
  // `cancelRefetch: false` : si l'effet repasse pendant qu'une page arrive,
  // on ne relance pas la requete en cours, on la laisse finir.
  //
  // Dependances : les champs utilises, jamais l'objet de requete entier, qui
  // change d'identite a chaque rendu. Et JAMAIS de relance apres un echec :
  // `hasNextPage` reste vrai quand la page suivante a echoue (il decoule de
  // la derniere page recue), donc sans cette garde l'effet relancerait la
  // requete a chaque echec, sans fin, des que le Wi-Fi tombe avec moins de
  // cinq cartes en pile. Le candidat relance lui-meme via « Reessayer ».
  const {
    hasNextPage: jeuncyHasNext,
    isFetchingNextPage: jeuncyFetchingNext,
    isError: jeuncyFailed,
    fetchNextPage: fetchNextJeuncy,
  } = jeuncy;
  const {
    hasNextPage: lbaHasNext,
    isFetchingNextPage: lbaFetchingNext,
    isError: lbaFailed,
    fetchNextPage: fetchNextLba,
  } = lba;

  useEffect(() => {
    if (!hydrated || deck.length >= RELOAD_BELOW) return;

    if (jeuncyHasNext) {
      if (!jeuncyFetchingNext && !jeuncyFailed) {
        void fetchNextJeuncy({ cancelRefetch: false });
      }

      return;
    }
    if (lbaHasNext && !lbaFetchingNext && !lbaFailed) {
      void fetchNextLba({ cancelRefetch: false });
    }
  }, [
    hydrated,
    deck.length,
    jeuncyHasNext,
    jeuncyFetchingNext,
    jeuncyFailed,
    fetchNextJeuncy,
    lbaHasNext,
    lbaFetchingNext,
    lbaFailed,
    fetchNextLba,
  ]);

  const hasMore = jeuncy.hasNextPage || lba.hasNextPage;
  const isFetching = jeuncy.isFetching || lba.isFetching;
  const error = jeuncy.error ?? lba.error;

  let status: DeckStatus;
  if (deck.length > 0) {
    status = 'ready';
  } else if (!hydrated || jeuncy.isPending || lba.isPending || (hasMore && isFetching)) {
    status = 'loading';
  } else if (error) {
    status = 'error';
  } else {
    status = 'empty';
  }

  const refresh = () => Promise.all([jeuncy.refetch(), lba.refetch()]);

  return { deck, cards, status, error, hasMore, isFetching, refresh };
}
