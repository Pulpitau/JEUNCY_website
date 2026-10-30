import { useQuery, useQueryClient } from '@tanstack/react-query';
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
// DEUX PILES, UNE SEULE REQUETE. Les offres Jeuncy sont une « Selection du
// jour » finie — au plus vingt — qui arrive en entier avec la reponse de
// base (la requete TanStack). Les offres partenaires arrivent par vingt.
//
// LA SUITE SE DEMANDE TOUJOURS A LA PAGE 1. Le serveur retire de sa reponse
// les offres deja jugees : une pagination par numero (page 2 = rangs 21-40)
// sautait donc toutes les offres qui avaient glisse vers le haut de la liste
// a mesure que le candidat jugeait les premieres — trente offres a portee,
// seize gestes, et la page 2 revenait vide avec dix offres jamais montrees.
// Redemander la page 1 rend « les vingt meilleures offres encore non
// jugees », et laisse le serveur basculer de lui-meme du rayon au
// departement puis a la France quand une portee est epuisee. Ce qui revient
// deja vu (cartes encore en pile, geste en route vers le serveur) est ecarte
// par la cle et par `decided` : une carte vue ne revient jamais.
//
// La suite vit dans un etat local rattache a la reponse de base : quand
// celle-ci est rechargee (invalidation apres un changement de rayon, de code
// postal, de photo), la suite repart de zero au lieu de melanger deux
// positions.
//
// TROIS GESTES, TROIS TRAITEMENTS.
//
// « Ca m'interesse » sur une offre Jeuncy part seul et tout de suite : il
// peut creer un match. « Passer » sur une offre Jeuncy s'accumule et part par
// lots ; une application tuee avant le vidage perd ces PASS et les cartes
// reviendront — c'est le bon sens de l'erreur. Les gestes sur une offre
// partenaire partent un par un, faute de route de lot.

/** Delai d'inactivite avant d'envoyer les « Passer » Jeuncy accumules. */
const PASS_FLUSH_MS = 1200;
/** En dessous de ce nombre de cartes restantes, on charge la suite. */
const RELOAD_BELOW = 5;
/** Reponses de suite sans offre nouvelle avant de declarer la pile finie. */
const MAX_REPONSES_VIDES = 2;

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
  id: number;
  decision: 'LIKE' | 'PASS' | 'KEEP';
}

/** Offres partenaires chargees apres la reponse de base. */
interface Suite {
  /** `dataUpdatedAt` de la reponse de base a laquelle cette suite appartient. */
  base: number;
  offers: DeckPartnerOffer[];
  meta: DeckMeta | null;
  /** Reponses consecutives sans offre nouvelle (0 = la derniere apportait du neuf). */
  vides: number;
  loading: boolean;
  error: unknown;
}

const suiteVide = (base: number, vides = 0): Suite => ({
  base,
  offers: [],
  meta: null,
  vides,
  loading: false,
  error: null,
});

export function useDiscoverDeck() {
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: DISCOVER_KEY,
    queryFn: () => discoverOffers(1),
  });
  const base = query.dataUpdatedAt;
  const reponse = query.data;

  // Une reponse de base sans offre partenaire dit deja que le serveur n'a
  // rien d'autre : inutile de redemander.
  const [suiteBrute, setSuite] = useState<Suite>(() => suiteVide(base));
  const suite = useMemo(
    () =>
      suiteBrute.base === base
        ? suiteBrute
        : suiteVide(
            base,
            reponse && reponse.partner.data.length === 0 ? MAX_REPONSES_VIDES : 0,
          ),
    [suiteBrute, base, reponse],
  );

  // Cartes retirees en attendant la confirmation du serveur. Le serveur
  // exclut deja ce qui a ete juge, mais entre le geste et le rechargement
  // c'est ce jeu qui tient la pile a jour.
  const [decided, setDecided] = useState<ReadonlySet<string>>(new Set());
  const [lastGesture, setLastGesture] = useState<LastGesture | null>(null);
  // Gestes partis au serveur sans reponse : l'annulation attend qu'ils
  // arrivent, sinon elle viserait le geste d'avant cote serveur.
  const [inFlight, setInFlight] = useState(0);
  // « Ca m'interesse » acceptes depuis la reponse de base : `meta.quota.used`
  // ne bouge qu'au rechargement, ce compteur le tient a jour entre-temps.
  const [likesBrut, setLikes] = useState({ base, n: 0 });
  const likes = likesBrut.base === base ? likesBrut.n : 0;

  const pending = useRef<number[]>([]);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const chargement = useRef(false);
  // « Passer » dont le lot n'a jamais atteint le serveur : les annuler ne
  // doit rien lui demander, son « dernier geste » designerait une autre carte.
  const sansGesteServeur = useRef(new Set<number>());
  // Une seule annulation a la fois : un double appui annulerait deux gestes
  // cote serveur pour une seule carte remise dans la pile.
  const annulationEnCours = useRef(false);
  const [undoing, setUndoing] = useState(false);

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
      // Silencieux : « Passer » n'a rien promis a personne.
      for (const id of lot) sansGesteServeur.current.add(id);
    }
  }, []);

  useEffect(() => () => void flushPasses(), [flushPasses]);

  // La derniere reponse dit la portee actuelle des partenaires (le serveur a
  // pu basculer du rayon au departement entre deux chargements).
  const meta: DeckMeta | null = suite.meta ?? reponse?.meta ?? null;

  const cards = useMemo(() => {
    const seen = new Set<string>();
    const result: DeckCard[] = [];
    const add = (card: DeckCard) => {
      if (seen.has(card.key)) return;
      seen.add(card.key);
      result.push(card);
    };

    for (const offer of reponse?.jeuncy ?? []) {
      add({ kind: 'jeuncy', key: deckKeyFor('jeuncy', offer.id), offer });
    }
    for (const offer of [...(reponse?.partner.data ?? []), ...suite.offers]) {
      add({ kind: 'lba', key: deckKeyFor('lba', offer.id), offer });
    }

    return result;
  }, [reponse, suite.offers]);

  const deck = useMemo(
    () => cards.filter((card) => !decided.has(card.key)),
    [cards, decided],
  );

  const chargerSuite = useCallback(async () => {
    if (chargement.current) return;
    chargement.current = true;
    const pourBase = base;
    setSuite((s) => ({
      ...(s.base === pourBase ? s : suiteVide(pourBase)),
      loading: true,
    }));

    try {
      const r = await discoverOffers(1);
      setSuite((s) => {
        // La base a ete rechargee entre-temps : cette suite n'a plus d'objet.
        if (s.base !== pourBase) return s;

        const connues = new Set([
          ...(reponse?.partner.data ?? []).map((o) => o.id),
          ...s.offers.map((o) => o.id),
        ]);
        const neuves = r.partner.data.filter((o) => !connues.has(o.id));

        return {
          ...s,
          offers: [...s.offers, ...neuves],
          meta: r.meta,
          vides:
            r.partner.data.length === 0
              ? MAX_REPONSES_VIDES
              : neuves.length > 0
                ? 0
                : s.vides + 1,
          loading: false,
          error: null,
        };
      });
    } catch (error) {
      setSuite((s) => (s.base === pourBase ? { ...s, loading: false, error } : s));
    } finally {
      chargement.current = false;
    }
  }, [base, reponse]);

  const retirer = (
    key: DeckKey,
    kind: 'jeuncy' | 'lba',
    id: number,
    decision: LastGesture['decision'],
  ) => {
    setDecided((courant) => new Set(courant).add(key));
    setLastGesture({ key, kind, id, decision });
  };

  const compterLike = (delta: number) =>
    setLikes((l) => ({ base, n: Math.max(0, (l.base === base ? l.n : 0) + delta) }));

  const remettre = (key: DeckKey) => {
    setDecided((courant) => {
      const suivant = new Set(courant);
      suivant.delete(key);

      return suivant;
    });
    setLastGesture(null);
  };

  const enVol = async <T>(appel: () => Promise<T>): Promise<T> => {
    setInFlight((n) => n + 1);
    try {
      return await appel();
    } finally {
      setInFlight((n) => n - 1);
    }
  };

  /**
   * « Ca m'interesse » sur une offre Jeuncy. Renvoie `matched` pour que
   * l'ecran annonce le match avec la carte encore sous les yeux.
   */
  const like = async (offer: DeckJeuncyOffer) => {
    const key = deckKeyFor('jeuncy', offer.id);
    retirer(key, 'jeuncy', offer.id, 'LIKE');

    try {
      const { matched } = await enVol(() => likeOffer(offer.id));
      compterLike(1);
      if (matched) void queryClient.invalidateQueries({ queryKey: MATCHES_KEY });

      return { matched };
    } catch (error) {
      // Quota atteint, offre depubliee : la carte revient, sinon le candidat
      // croirait son geste enregistre.
      remettre(key);
      throw error;
    }
  };

  const pass = (offer: DeckJeuncyOffer) => {
    retirer(deckKeyFor('jeuncy', offer.id), 'jeuncy', offer.id, 'PASS');
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
    retirer(key, 'lba', offer.id, decision);

    try {
      return await enVol(() => decideExternalOffer(offer.id, decision));
    } catch (error) {
      remettre(key);
      throw error;
    }
  };

  /**
   * Annule le dernier geste, du bon cote. Renvoie la pile visee, pour que
   * l'ecran rafraichisse ce qui en depend (les offres gardees).
   *
   * Un « Passer » encore dans le tampon n'est jamais parti : on le retire du
   * tampon, sans rien demander au serveur. Sinon le dernier geste est connu
   * du serveur, et c'est le dernier qu'il a recu — on ne vide surtout PAS le
   * tampon avant : des « Passer » plus anciens, envoyes a ce moment-la,
   * deviendraient le « dernier geste » du serveur a la place du bon.
   */
  const undo = async (): Promise<'jeuncy' | 'lba' | null> => {
    // Les gestes en route (inFlight) doivent arriver avant : sinon le
    // « dernier geste » du serveur serait le precedent.
    if (lastGesture === null || inFlight > 0 || annulationEnCours.current) return null;

    const { key, kind, id, decision } = lastGesture;
    annulationEnCours.current = true;
    setUndoing(true);

    try {
      if (kind === 'jeuncy') {
        const enAttente = pending.current.indexOf(id);
        if (enAttente !== -1) {
          // Jamais parti : on le retire du tampon, rien a demander au serveur.
          pending.current.splice(enAttente, 1);
        } else if (sansGesteServeur.current.has(id)) {
          // Lot perdu : le serveur ne connait pas ce geste.
          sansGesteServeur.current.delete(id);
        } else {
          await undoLastInterest();
          if (decision === 'LIKE') compterLike(-1);
        }
      } else {
        await undoLastExternal();
      }

      remettre(key);

      return kind;
    } finally {
      annulationEnCours.current = false;
      setUndoing(false);
    }
  };

  // Chargement de la suite. Deux declencheurs : sous cinq cartes, si la
  // derniere reponse apportait du neuf ; pile vide, si aucun geste n'est en
  // route — c'est le moment ou le serveur, sa portee epuisee, bascule au
  // departement puis a la France. Deux reponses de suite sans nouveaute
  // arretent tout, et jamais apres un echec : pas de boucle sur un reseau
  // coupe.
  const peutCharger =
    query.isSuccess && !suite.loading && !suite.error && suite.vides < MAX_REPONSES_VIDES;

  useEffect(() => {
    if (!peutCharger) return;

    const anticipe = deck.length < RELOAD_BELOW && suite.vides === 0;
    const relance = deck.length === 0 && inFlight === 0;
    if (anticipe || relance) void chargerSuite();
  }, [peutCharger, deck.length, suite.vides, inFlight, chargerSuite]);

  let status: DeckStatus;
  if (deck.length > 0) {
    status = 'ready';
  } else if (query.isPending || suite.loading || (peutCharger && query.isSuccess)) {
    status = 'loading';
  } else if (query.error || suite.error) {
    status = 'error';
  } else {
    status = 'empty';
  }

  // « Ca m'interesse » restants aujourd'hui ; null quand le quota ne
  // s'applique pas (pile trop maigre pour qu'il protege quoi que ce soit).
  const quota = reponse?.meta.quota;
  const likesRestants =
    quota && quota.active ? Math.max(0, quota.limit - quota.used - likes) : null;

  const canUndo =
    lastGesture !== null &&
    inFlight === 0 &&
    !undoing &&
    cards.some((card) => card.key === lastGesture.key);

  const refresh = async () => {
    // Le tampon part avant le rechargement : sinon les cartes ecartees
    // reviendraient dans la reponse, et le geste serait perdu.
    await flushPasses();
    setDecided(new Set());
    setLastGesture(null);
    setSuite(suiteVide(-1));

    return query.refetch();
  };

  return {
    deck,
    cards,
    meta,
    status,
    error: query.error ?? suite.error,
    canUndo,
    likesRestants,
    like,
    pass,
    decideExternal,
    undo,
    refresh,
    isRefreshing: query.isRefetching,
  };
}
