import { useInfiniteQuery, useQueryClient } from '@tanstack/react-query';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import { MATCHES_KEY } from '@/hooks/use-matches';
import {
  discoverOffers,
  type DeckJeuncyOffer,
  type DeckMeta,
  type DeckPartnerOffer,
} from '@/lib/api/discover';
import {
  decideExternalOffer,
  undoLastExternal,
  type ExternalDecision,
} from '@/lib/api/external-interests';
import {
  likeOffer,
  passOffers,
  undoLastInterest,
  PASS_BATCH_MAX,
} from '@/lib/api/interests';

// La pile « Decouvrir » du candidat, branchee sur `discover/offers`.
//
// Elle remplace le prototype du 2026-09-22, dont les gestes ne quittaient
// pas le telephone (swipe-store.ts). Deux differences de fond : le serveur
// decide de la selection, et un « Ca m'interesse » peut desormais creer un
// match.
//
// DEUX PILES, DEUX REGIMES DE PAGINATION. Les offres Jeuncy sont une
// « Selection du jour » finie — au plus vingt, renouvelee apres l'import de
// la nuit — et arrivent EN ENTIER des la premiere page. Les offres
// partenaires, elles, se paginent par vingt : il y en a 7 779 en production
// contre une seule offre Jeuncy, et s'arreter a vingt pour tout le monde
// viderait la pile en une minute. D'ou la regle a ne pas perdre de vue : on
// ne lit `jeuncy` QUE sur la premiere page, sinon les vingt memes offres
// reviendraient a chaque page suivante.
//
// TROIS GESTES, TROIS TRAITEMENTS.
//
// « Ca m'interesse » sur une offre Jeuncy part seul et tout de suite : il
// peut creer un match, donc une notification et un email chez l'employeur.
//
// « Passer » sur une offre Jeuncy s'accumule et part par lots : le doigt
// enchaine plus vite que le reseau. Une application tuee avant le vidage
// perd les PASS en attente et ces cartes reviendront — c'est le bon sens de
// l'erreur.
//
// Les gestes sur une offre partenaire partent un par un : il n'existe pas de
// route de lot, et « Je garde » cree une ligne que l'ecran Candidatures doit
// pouvoir afficher dans la seconde.

/** Delai d'inactivite avant d'envoyer les « Passer » Jeuncy accumules. */
const PASS_FLUSH_MS = 1200;
/** En dessous de ce nombre de cartes restantes, on charge la page partenaire suivante. */
const RELOAD_BELOW = 5;

export const DISCOVER_KEY = ['discover', 'offers'] as const;

/** `jeuncy:ID` pour une offre Jeuncy, `lba:ID` pour une offre partenaire. */
export type DeckKey = `jeuncy:${number}` | `lba:${number}`;

export type DeckCard =
  | { kind: 'jeuncy'; key: DeckKey; offer: DeckJeuncyOffer }
  | { kind: 'lba'; key: DeckKey; offer: DeckPartnerOffer };

export type DeckStatus = 'loading' | 'error' | 'empty' | 'ready';

export function deckKeyFor(kind: 'jeuncy' | 'lba', id: number): DeckKey {
  return kind === 'jeuncy' ? `jeuncy:${id}` : `lba:${id}`;
}

interface LastGesture {
  key: DeckKey;
  kind: 'jeuncy' | 'lba';
}

export function useDiscoverDeck() {
  const queryClient = useQueryClient();

  const query = useInfiniteQuery({
    queryKey: DISCOVER_KEY,
    queryFn: ({ pageParam }) => discoverOffers(pageParam),
    initialPageParam: 1,
    getNextPageParam: (last) =>
      last.partner.current_page < last.partner.last_page
        ? last.partner.current_page + 1
        : undefined,
  });

  // Cartes retirees en attendant la confirmation du serveur. Le serveur
  // exclut deja ce qui a ete juge, mais entre le geste et le rechargement
  // c'est ce jeu qui tient la pile a jour.
  const [decided, setDecided] = useState<ReadonlySet<string>>(new Set());
  const [lastGesture, setLastGesture] = useState<LastGesture | null>(null);

  const pending = useRef<number[]>([]);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const flushPasses = useCallback(async () => {
    if (timer.current) {
      clearTimeout(timer.current);
      timer.current = null;
    }

    const lot = pending.current;
    if (lot.length === 0) return;

    // Vide AVANT l'appel : un envoi rate coute quelques cartes revues, une
    // file qui se rejoue sans fin sur un reseau coupe coute bien plus.
    pending.current = [];

    try {
      await passOffers(lot);
    } catch {
      // Silencieux : « Passer » n'a rien promis a personne, et une alerte au
      // milieu d'un parcours de pile serait pire que la carte revue.
    }
  }, []);

  useEffect(() => () => void flushPasses(), [flushPasses]);

  const pages = query.data?.pages;
  const meta: DeckMeta | null = pages?.[0]?.meta ?? null;

  const cards = useMemo(() => {
    const seen = new Set<string>();
    const result: DeckCard[] = [];
    const add = (card: DeckCard) => {
      if (seen.has(card.key)) return;
      seen.add(card.key);
      result.push(card);
    };

    // Selection du jour : premiere page uniquement (voir l'en-tete).
    for (const offer of pages?.[0]?.jeuncy ?? []) {
      add({ kind: 'jeuncy', key: deckKeyFor('jeuncy', offer.id), offer });
    }
    for (const page of pages ?? []) {
      for (const offer of page.partner.data) {
        add({ kind: 'lba', key: deckKeyFor('lba', offer.id), offer });
      }
    }

    return result;
  }, [pages]);

  const deck = useMemo(
    () => cards.filter((card) => !decided.has(card.key)),
    [cards, decided],
  );

  const retirer = (key: DeckKey, kind: 'jeuncy' | 'lba') => {
    setDecided((courant) => new Set(courant).add(key));
    setLastGesture({ key, kind });
  };

  const remettre = (key: DeckKey) => {
    setDecided((courant) => {
      const suivant = new Set(courant);
      suivant.delete(key);

      return suivant;
    });
    setLastGesture(null);
  };

  /**
   * « Ca m'interesse » sur une offre Jeuncy.
   *
   * Renvoie `matched` pour que l'ecran annonce le match avec la carte encore
   * sous les yeux : c'est le seul moment ou il peut le faire.
   */
  const like = async (offer: DeckJeuncyOffer) => {
    const key = deckKeyFor('jeuncy', offer.id);
    retirer(key, 'jeuncy');

    try {
      const { matched } = await likeOffer(offer.id);
      if (matched) void queryClient.invalidateQueries({ queryKey: MATCHES_KEY });

      return { matched };
    } catch (error) {
      // Quota atteint, offre depubliee, age insuffisant : la carte revient,
      // sinon le candidat croirait son geste enregistre.
      remettre(key);
      throw error;
    }
  };

  const pass = (offer: DeckJeuncyOffer) => {
    retirer(deckKeyFor('jeuncy', offer.id), 'jeuncy');
    pending.current.push(offer.id);

    if (pending.current.length >= PASS_BATCH_MAX) {
      void flushPasses();

      return;
    }
    if (timer.current) clearTimeout(timer.current);
    timer.current = setTimeout(() => void flushPasses(), PASS_FLUSH_MS);
  };

  /** « Je garde » ou « Passer » sur une offre partenaire. */
  const decideExternal = async (offer: DeckPartnerOffer, decision: ExternalDecision) => {
    const key = deckKeyFor('lba', offer.id);
    retirer(key, 'lba');

    try {
      return await decideExternalOffer(offer.id, decision);
    } catch (error) {
      remettre(key);
      throw error;
    }
  };

  /**
   * Annule le dernier geste, du bon cote.
   *
   * Les deux piles ont chacune leur route d'annulation, et le serveur ne
   * garde qu'un cran par pile : il faut donc savoir laquelle viser. Le
   * tampon des « Passer » Jeuncy part d'abord, sinon « le dernier geste »
   * designerait une autre carte cote serveur.
   */
  const undo = async () => {
    if (lastGesture === null) return;

    await flushPasses();

    if (lastGesture.kind === 'jeuncy') {
      await undoLastInterest();
    } else {
      await undoLastExternal();
    }

    remettre(lastGesture.key);
  };

  // Rechargement anticipe de la pile partenaire. Jamais apres un echec :
  // `hasNextPage` reste vrai quand la page suivante a echoue, et l'effet
  // relancerait la requete sans fin des que le reseau tombe.
  const { hasNextPage, isFetchingNextPage, isError, fetchNextPage } = query;

  useEffect(() => {
    if (deck.length >= RELOAD_BELOW) return;
    if (!hasNextPage || isFetchingNextPage || isError) return;

    void fetchNextPage({ cancelRefetch: false });
  }, [deck.length, hasNextPage, isFetchingNextPage, isError, fetchNextPage]);

  let status: DeckStatus;
  if (deck.length > 0) {
    status = 'ready';
  } else if (query.isPending || (hasNextPage && query.isFetching)) {
    status = 'loading';
  } else if (query.error) {
    status = 'error';
  } else {
    status = 'empty';
  }

  const canUndo =
    lastGesture !== null && cards.some((card) => card.key === lastGesture.key);

  const refresh = async () => {
    // Le tampon part avant le rechargement : sinon les cartes ecartees
    // reviendraient dans la reponse, et le geste serait perdu.
    await flushPasses();
    setDecided(new Set());
    setLastGesture(null);

    return query.refetch();
  };

  return {
    deck,
    cards,
    meta,
    status,
    error: query.error,
    canUndo,
    like,
    pass,
    decideExternal,
    undo,
    refresh,
    isRefreshing: query.isRefetching,
  };
}
