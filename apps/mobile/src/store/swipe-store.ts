import AsyncStorage from '@react-native-async-storage/async-storage';
import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';

// STOCKAGE LOCAL DU PROTOTYPE — a remplacer par la table `offer_interests`
// (lot 1-3 du plan match). Les gestes du candidat ne quittent pas le
// telephone : aucune entreprise n'est prevenue, aucun match ne peut naitre.
// Le prototype sert a valider le geste, la carte et le rythme de la pile sur
// un vrai iPhone, pas le modele de donnees. Le jour ou l'API existe, ce store
// devient un simple cache des decisions renvoyees par le serveur.
//
// AsyncStorage et non SecureStore, comme la preference de theme : ce n'est
// pas un secret, et le coffre est lent.

export type SwipeDecision = 'PASS' | 'INTEREST' | 'KEEP';

/** `jeuncy:ID` pour une offre Jeuncy, `lba:ID` pour une offre partenaire. */
export type SwipeKey = `jeuncy:${number}` | `lba:${number}`;

/**
 * Ce qu'il faut pour afficher une offre partenaire « gardee » sans la
 * recharger : l'import LBA supprime chaque nuit les offres absentes de
 * l'export, la ligne doit survivre a l'offre (meme raison que le
 * denormalise prevu pour `external_interests`).
 */
export interface KeptOfferSnapshot {
  id: number;
  title: string;
  employer: string | null;
  city: string | null;
  applyUrl: string;
}

export interface SwipeGesture {
  key: SwipeKey;
  decision: SwipeDecision;
  /** Horodatage ISO 8601 du geste. */
  at: string;
  /** Renseigne uniquement pour un KEEP sur une offre partenaire. */
  kept?: KeptOfferSnapshot;
}

interface SwipeState {
  gestures: Record<string, SwipeGesture>;
  /** Dernier geste, annulable tant qu'un autre ne l'a pas remplace. Non persiste. */
  lastGesture: SwipeGesture | null;
  /** Departement de la selection (offres partenaires). Defaut : Pyrenees-Orientales. */
  department: string;
  /** true une fois AsyncStorage relu : avant, la pile afficherait des cartes deja vues. */
  hydrated: boolean;
  record: (gesture: Omit<SwipeGesture, 'at'>) => void;
  /** Annule le dernier geste et le renvoie ; null s'il n'y a rien a annuler. */
  undo: () => SwipeGesture | null;
  /** Oublie un geste precis (retrait d'une offre gardee). */
  forget: (key: SwipeKey) => void;
  setDepartment: (department: string) => void;
  setHydrated: () => void;
}

export const DEFAULT_DEPARTMENT = '66';

export const useSwipeStore = create<SwipeState>()(
  persist(
    (set, get) => ({
      gestures: {},
      lastGesture: null,
      department: DEFAULT_DEPARTMENT,
      hydrated: false,
      record: (gesture) => {
        const complet: SwipeGesture = { ...gesture, at: new Date().toISOString() };
        set((state) => ({
          gestures: { ...state.gestures, [gesture.key]: complet },
          lastGesture: complet,
        }));
      },
      undo: () => {
        const dernier = get().lastGesture;
        if (!dernier) return null;

        set((state) => {
          const { [dernier.key]: _retire, ...reste } = state.gestures;

          return { gestures: reste, lastGesture: null };
        });

        return dernier;
      },
      forget: (key) =>
        set((state) => {
          const { [key]: _retire, ...reste } = state.gestures;

          return {
            gestures: reste,
            lastGesture: state.lastGesture?.key === key ? null : state.lastGesture,
          };
        }),
      setDepartment: (department) => set({ department }),
      setHydrated: () => set({ hydrated: true }),
    }),
    {
      name: 'jeuncy.swipe-prototype',
      storage: createJSONStorage(() => AsyncStorage),
      // Seuls les gestes et le departement survivent au redemarrage : le
      // dernier geste annulable ne vaut que pour la session en cours.
      partialize: (state) => ({ gestures: state.gestures, department: state.department }),
      onRehydrateStorage: () => () => {
        // Appele aussi en cas d'echec de lecture, mais alors SANS etat en
        // argument (zustand passe `undefined` et l'erreur) : on passe donc
        // par le store lui-meme, sinon `hydrated` resterait faux et la pile
        // afficherait « On prepare ta selection… » pour toujours. Un echec
        // fait repartir de zero, ce qui vaut mieux qu'un ecran bloque.
        useSwipeStore.getState().setHydrated();
      },
    },
  ),
);

export function swipeKeyFor(kind: 'jeuncy' | 'lba', id: number): SwipeKey {
  return kind === 'jeuncy' ? `jeuncy:${id}` : `lba:${id}`;
}

/** Offres partenaires gardees, la plus recente en premier. */
export function keptOffers(gestures: Record<string, SwipeGesture>): SwipeGesture[] {
  return Object.values(gestures)
    .filter((gesture) => gesture.decision === 'KEEP' && gesture.kept !== undefined)
    .sort((a, b) => b.at.localeCompare(a.at));
}
