import { useInfiniteQuery, useQueryClient } from '@tanstack/react-query';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import { discoverCandidates, type DeckCandidateCard } from '@/lib/api/discover';
import {
  likeCandidate,
  passCandidates,
  undoLastInterest,
  PASS_BATCH_MAX,
} from '@/lib/api/interests';
import { MATCHES_KEY } from '@/hooks/use-matches';

// La pile « Decouvrir » cote employeur, branchee sur le vrai serveur — c'est
// ce qui la distingue du prototype candidat du 2026-09-22, dont les gestes
// ne quittaient pas le telephone.
//
// DEUX GESTES, DEUX TRAITEMENTS.
//
// « Ca m'interesse » part tout de suite, seul : il peut creer un match, donc
// declencher une notification et un email chez le candidat. Grouper ce
// geste-la retarderait l'evenement que les deux parties attendent, et
// l'ecran ne saurait pas quoi annoncer au moment du glissement.
//
// « Passer » n'interesse personne d'autre que la pile. Il s'accumule et part
// par lots (`interests/batch`) : le doigt enchaine plus vite que le reseau,
// et un aller-retour par carte ecartee transformerait une pile parcourue en
// vingt requetes. Le prix a payer est explicite : une application tuee avant
// le vidage perd les PASS en attente, et ces cartes reviendront. C'est le bon
// sens de l'erreur — un candidat revu est un desagrement, un candidat ecarte
// par un geste jamais arrive serait une perte.
//
// L'ANNULATION VIDE D'ABORD. `DELETE interests/last` annule le dernier geste
// *du serveur*. Si des PASS attendent encore dans le tampon, le dernier geste
// du serveur n'est pas celui que l'employeur vient de faire : on viderait le
// tampon apres coup et l'annulation aurait porte sur la mauvaise carte.

/** Delai d'inactivite avant d'envoyer les « Passer » accumules. */
const PASS_FLUSH_MS = 1200;
/** En dessous de ce nombre de cartes restantes, on charge la page suivante. */
const RELOAD_BELOW = 5;

export const CANDIDATE_DECK_KEY = ['discover', 'candidates'] as const;

export type DeckStatus = 'loading' | 'error' | 'empty' | 'ready';

export interface DeckGesture {
  candidateProfileId: number;
  decision: 'LIKE' | 'PASS';
}

/**
 * Gestes en attente, avec l'offre a laquelle ils appartiennent.
 *
 * `decided` : cartes retirees de la pile en attendant que le serveur
 * confirme. Un identifiant suffit — la carte reste dans le cache de la
 * requete, ce qui permet de la remettre en tete si l'employeur annule.
 */
interface GestureState {
  offerId: number | null;
  decided: ReadonlySet<number>;
  last: DeckGesture | null;
}

function vierge(offerId: number | null): GestureState {
  return { offerId, decided: new Set(), last: null };
}

export function useCandidateDeck(jobOfferId: number | null) {
  const queryClient = useQueryClient();

  const query = useInfiniteQuery({
    queryKey: [...CANDIDATE_DECK_KEY, jobOfferId],
    queryFn: ({ pageParam }) => discoverCandidates(jobOfferId ?? 0, pageParam),
    initialPageParam: 1,
    getNextPageParam: (last) =>
      last.current_page < last.last_page ? last.current_page + 1 : undefined,
    enabled: jobOfferId !== null,
    // Les cartes deja jugees sont retirees cote serveur : une pile rechargee
    // ne remontre pas ce qui vient d'etre ecarte, il n'y a donc rien a
    // reconcilier entre le cache et le tampon local.
    staleTime: 60_000,
  });

  // Gestes en attente de confirmation serveur, INDEXES PAR OFFRE.
  //
  // L'identifiant de l'offre est dans l'etat lui-meme plutot que remis a
  // zero par un effet : changer d'offre change de pile, et un effet qui
  // appelle setState provoque un rendu en cascade — la pile de la nouvelle
  // offre s'afficherait une image avec les cartes ecartees de l'ancienne.
  // Ici, l'etat d'une autre offre est simplement ignore au rendu.
  const [gestures, setGestures] = useState<GestureState>(() => vierge(jobOfferId));
  const courant = gestures.offerId === jobOfferId ? gestures : vierge(jobOfferId);
  const decided = courant.decided;
  const lastGesture = courant.last;

  // Tampon des « Passer ». Une ref et non un state : le vidage ne doit pas
  // dependre d'un rendu, et un lot en cours d'envoi ne doit pas repartir
  // parce que le composant s'est redessine.
  const pending = useRef<number[]>([]);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const flushPasses = useCallback(async () => {
    if (timer.current) {
      clearTimeout(timer.current);
      timer.current = null;
    }

    const lot = pending.current;
    if (lot.length === 0 || jobOfferId === null) return;

    // Vide AVANT l'appel : si l'envoi echoue, ces cartes sont perdues pour
    // cette session plutot que renvoyees en boucle. Le serveur les
    // reproposera, ce qui est recuperable ; une boucle de requetes ratees
    // sur un reseau coupe ne l'est pas.
    pending.current = [];

    try {
      await passCandidates(jobOfferId, lot);
    } catch {
      // Silencieux a dessein : « Passer » n'a rien promis a personne. Le
      // signaler ouvrirait une alerte au milieu d'un parcours de pile.
    }
  }, [jobOfferId]);

  // Vidage au demontage et au changement d'offre : c'est le dernier moment
  // ou le lot peut encore partir. Le nettoyage s'execute avec le
  // `flushPasses` du rendu precedent, donc avec l'ANCIENNE offre — celle a
  // laquelle appartiennent les « Passer » en attente.
  useEffect(() => () => void flushPasses(), [flushPasses]);

  const pages = query.data?.pages;

  const cards = useMemo(() => {
    const seen = new Set<number>();
    const result: DeckCandidateCard[] = [];

    for (const page of pages ?? []) {
      for (const card of page.data) {
        // Une meme carte peut revenir sur deux pages si la pile a bouge
        // entre les deux requetes ; deux cles identiques feraient crier React.
        if (seen.has(card.id)) continue;
        seen.add(card.id);
        result.push(card);
      }
    }

    return result;
  }, [pages]);

  const deck = useMemo(
    () => cards.filter((card) => !decided.has(card.id)),
    [cards, decided],
  );

  // Les deux ecritures repartent de l'etat de CETTE offre : un etat laisse
  // par une autre offre ne doit jamais se melanger a celui-ci.
  const majGestes = (
    transformer: (etat: GestureState) => Omit<GestureState, 'offerId'>,
  ) =>
    setGestures((precedent) => ({
      offerId: jobOfferId,
      ...transformer(precedent.offerId === jobOfferId ? precedent : vierge(jobOfferId)),
    }));

  const retirer = (id: number, geste: DeckGesture | null) =>
    majGestes((etat) => ({
      decided: new Set(etat.decided).add(id),
      last: geste,
    }));

  const remettre = (id: number) =>
    majGestes((etat) => {
      const suivant = new Set(etat.decided);
      suivant.delete(id);

      return { decided: suivant, last: null };
    });

  /**
   * « Ca m'interesse ». Renvoie `matched` pour que l'ecran annonce le match
   * dans la foulee — c'est le seul moment ou il peut le faire avec la carte
   * encore sous les yeux.
   */
  const like = async (card: DeckCandidateCard) => {
    if (jobOfferId === null) return { matched: false, applicationSent: false };

    retirer(card.id, { candidateProfileId: card.id, decision: 'LIKE' });

    try {
      const { matched, interest } = await likeCandidate(jobOfferId, card.id);

      if (matched) {
        // Le match vient d'exister : la liste des matchs est perimee.
        void queryClient.invalidateQueries({ queryKey: MATCHES_KEY });
      }

      // Un dossier deja envoye est rattache au match par le serveur : le
      // match nait alors « dossier envoye », et l'employeur n'a plus rien a
      // attendre du candidat. L'annoncer autrement le ferait patienter pour
      // rien.
      return { matched, applicationSent: interest.application_id !== null };
    } catch (error) {
      // Quota atteint, candidat devenu ineligible, offre depubliee : la
      // carte revient, sinon l'employeur croirait son geste enregistre.
      remettre(card.id);
      throw error;
    }
  };

  const pass = (card: DeckCandidateCard) => {
    retirer(card.id, { candidateProfileId: card.id, decision: 'PASS' });
    pending.current.push(card.id);

    if (pending.current.length >= PASS_BATCH_MAX) {
      void flushPasses();

      return;
    }
    if (timer.current) clearTimeout(timer.current);
    timer.current = setTimeout(() => void flushPasses(), PASS_FLUSH_MS);
  };

  const undo = async () => {
    if (lastGesture === null) return;

    // Voir l'en-tete : le tampon part d'abord, sinon « le dernier geste »
    // designe une autre carte cote serveur.
    await flushPasses();
    await undoLastInterest();

    // `remettre` efface aussi le dernier geste : il n'y a plus rien a
    // annuler apres, le serveur ne gardant qu'un cran d'annulation.
    remettre(lastGesture.candidateProfileId);
  };

  // Rechargement anticipe. Jamais apres un echec : `hasNextPage` reste vrai
  // quand la page suivante a echoue, l'effet relancerait la requete sans fin
  // des que le reseau tombe avec moins de cinq cartes en pile.
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

  // Annulable seulement si la carte est encore chargee : sinon elle ne
  // pourrait pas revenir en tete de pile.
  const canUndo =
    lastGesture !== null &&
    cards.some((card) => card.id === lastGesture.candidateProfileId);

  return {
    deck,
    status,
    error: query.error,
    canUndo,
    like,
    pass,
    undo,
    refresh: () => query.refetch(),
  };
}
