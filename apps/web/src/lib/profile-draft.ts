import type {
  Education,
  Experience,
  Language,
  Skill,
  Software,
} from '@/lib/api/candidate-profile';

// Brouillon local de la page « Mon profil ».
//
// Le probleme corrige (signale par Pierre le 2026-09-23) : la page se remplit
// en plusieurs fois — 14 champs d'identite, plus experiences, formations,
// langues, competences et logiciels — et tant que rien n'est enregistre, tout
// vit dans l'etat React. Quitter la page, ou la fermer, effacait tout.
//
// localStorage, apres un premier essai rate en sessionStorage : celui-ci meurt
// avec l'onglet, donc il couvrait la navigation mais pas le geste que fait
// vraiment le candidat — ouvrir une autre page, la FERMER, revenir (retour de
// Pierre le 2026-09-23, « ca marche toujours pas »). Un brouillon qui ne
// survit pas a la fermeture d'un onglet ne sert a rien.
//
// Ce brouillon contient un nom, une date de naissance, un telephone et une
// adresse, et le public de Jeuncy navigue souvent depuis un poste partage
// (CFA, lycee, mission locale). Trois garde-fous, plutot que de renoncer :
//   - la cle porte l'identifiant du compte, donc aucun candidat ne peut voir
//     le brouillon d'un autre ;
//   - la deconnexion efface tout (clearAllProfileDrafts, appelee par
//     clearSession dans store/auth-store.ts) ;
//   - un brouillon de plus de sept jours est jete a la lecture, sans quoi des
//     donnees personnelles dormiraient indefiniment sur la machine (RGPD,
//     CLAUDE.md section 10 : ne rien garder plus longtemps qu'utile).
// A noter : sur un poste partage, le cookie de session vit deja sept jours —
// le brouillon n'ouvre donc pas une porte que le site laissait fermee.

const PREFIX = 'jeuncy.profil-brouillon.';

// Sept jours : assez pour reprendre son profil le week-end suivant, assez
// court pour qu'un brouillon oublie ne traine pas.
const MAX_AGE_MS = 7 * 24 * 60 * 60 * 1000;

export interface ProfileDraft {
  // Les champs du formulaire d'identite, tels que react-hook-form les tient :
  // des chaines, jamais null (le formulaire convertit en null a l'envoi).
  info?: Record<string, string>;
  experiences?: Experience[];
  educations?: Education[];
  languages?: Language[];
  skills?: Skill[];
  software?: Software[];
  // Date de derniere ecriture (millisecondes). Absente sur un brouillon ecrit
  // par la version precedente : on le considere alors comme perime.
  savedAt?: number;
}

function keyFor(userId: string): string {
  return `${PREFIX}${userId}`;
}

// Tout passe par ici : l'acces au stockage peut lever (Safari en navigation
// privee, quota depasse, stockage desactive par une politique
// d'etablissement). Un brouillon indisponible ne doit jamais casser la page —
// on retombe simplement sur le comportement d'avant.
function storage(): Storage | null {
  try {
    return window.localStorage;
  } catch {
    return null;
  }
}

export function readProfileDraft(userId: string): ProfileDraft | null {
  const store = storage();
  if (!store) {
    return null;
  }

  try {
    const raw = store.getItem(keyFor(userId));
    if (!raw) {
      return null;
    }

    const parsed: unknown = JSON.parse(raw);
    if (!parsed || typeof parsed !== 'object') {
      return null;
    }

    const draft = parsed as ProfileDraft;

    // Perime : on l'efface au passage plutot que de le laisser dormir sur la
    // machine. Un brouillon sans date vient d'une version anterieure.
    if (typeof draft.savedAt !== 'number' || Date.now() - draft.savedAt > MAX_AGE_MS) {
      store.removeItem(keyFor(userId));

      return null;
    }

    return draft;
  } catch {
    // JSON corrompu (ecriture interrompue, bricolage manuel) : on repart d'une
    // page vierge plutot que de propager une exception au rendu.
    return null;
  }
}

// Fusion et pas remplacement : le formulaire d'identite et les sections
// ecrivent dans le meme brouillon, chacun de son cote. Tout est synchrone,
// donc il n'y a pas de course entre les deux.
export function patchProfileDraft(userId: string, patch: Partial<ProfileDraft>): void {
  const store = storage();
  if (!store) {
    return;
  }

  const next = { ...(readProfileDraft(userId) ?? {}), ...patch, savedAt: Date.now() };

  try {
    // Un brouillon qui ne contient plus rien est retire plutot que reecrit
    // vide : c'est le cas juste apres un enregistrement reussi, et une entree
    // fantome rouvrirait le formulaire pour rien au prochain passage.
    if (!draftHasInfo(next) && !draftHasSections(next)) {
      store.removeItem(keyFor(userId));

      return;
    }

    store.setItem(keyFor(userId), JSON.stringify(next));
  } catch {
    // Quota atteint ou ecriture refusee : le brouillon est un confort, pas une
    // condition de l'enregistrement. On continue sans lui.
    return;
  }
}

export function clearProfileDraft(userId: string): void {
  const store = storage();
  if (!store) {
    return;
  }

  try {
    store.removeItem(keyFor(userId));
  } catch {
    // Un brouillon qu'on ne peut pas effacer disparaitra de toute facon avec
    // l'onglet.
    return;
  }
}

// Appelee a la deconnexion : on ne connait pas forcement le compte qui part
// (le store est deja vide dans certains chemins), et de toute facon aucun
// brouillon n'a de raison de survivre a une fin de session.
export function clearAllProfileDrafts(): void {
  const store = storage();
  if (!store) {
    return;
  }

  try {
    const keys: string[] = [];
    for (let index = 0; index < store.length; index += 1) {
      const key = store.key(index);
      if (key?.startsWith(PREFIX)) {
        keys.push(key);
      }
    }

    // Suppression apres la boucle : retirer une cle pendant qu'on parcourt
    // l'index decale les suivantes et en sauterait la moitie.
    keys.forEach((key) => store.removeItem(key));
  } catch {
    return;
  }
}

// Un brouillon « vide » (l'utilisateur a juste ouvert la page) ne doit pas
// declencher le bandeau de restauration ni rouvrir le formulaire.
export function draftHasInfo(draft: ProfileDraft | null): boolean {
  return Object.values(draft?.info ?? {}).some(
    (value) => typeof value === 'string' && value.trim() !== '',
  );
}

export function draftHasSections(draft: ProfileDraft | null): boolean {
  if (!draft) {
    return false;
  }

  return (
    (draft.experiences?.length ?? 0) > 0 ||
    (draft.educations?.length ?? 0) > 0 ||
    (draft.languages?.length ?? 0) > 0 ||
    (draft.skills?.length ?? 0) > 0 ||
    (draft.software?.length ?? 0) > 0
  );
}
