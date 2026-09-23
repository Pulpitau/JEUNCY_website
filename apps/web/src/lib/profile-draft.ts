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
// vit dans l'etat React. Ouvrir une offre ou revenir a l'accueil demonte la
// page ; au retour, le candidat retrouvait un formulaire vide et devait tout
// retaper. Le brouillon survit desormais a cette navigation.
//
// sessionStorage plutot que localStorage, et c'est un choix, pas un defaut :
// ce brouillon contient un nom, une date de naissance, un telephone et une
// adresse. Le public de Jeuncy navigue souvent depuis un poste partage (CFA,
// lycee, mission locale) ; un brouillon qui survivrait a la fermeture du
// navigateur exposerait ces donnees au visiteur suivant. sessionStorage meurt
// avec l'onglet, ce qui couvre exactement le cas signale sans laisser de
// trainee. (RGPD, CLAUDE.md section 10 : minimiser ce qu'on conserve.)
//
// La cle porte l'identifiant du compte : deux comptes qui se succedent dans le
// meme onglet ne peuvent pas heriter du brouillon l'un de l'autre. Et la
// deconnexion efface tout (voir clearAllProfileDrafts, appelee par
// clearSession dans store/auth-store.ts).

const PREFIX = 'jeuncy.profil-brouillon.';

export interface ProfileDraft {
  // Les champs du formulaire d'identite, tels que react-hook-form les tient :
  // des chaines, jamais null (le formulaire convertit en null a l'envoi).
  info?: Record<string, string>;
  experiences?: Experience[];
  educations?: Education[];
  languages?: Language[];
  skills?: Skill[];
  software?: Software[];
}

function keyFor(userId: string): string {
  return `${PREFIX}${userId}`;
}

// Tout passe par ici : l'acces a sessionStorage peut lever (Safari en
// navigation privee, quota depasse, stockage desactive par une politique
// d'etablissement). Un brouillon indisponible ne doit jamais casser la page —
// on retombe simplement sur le comportement d'avant.
function storage(): Storage | null {
  try {
    return window.sessionStorage;
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

    return parsed && typeof parsed === 'object' ? (parsed as ProfileDraft) : null;
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

  const next = { ...(readProfileDraft(userId) ?? {}), ...patch };

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
