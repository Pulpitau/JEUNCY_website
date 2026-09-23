import type { CandidateCard } from '@/lib/api/cvtheque';

// Comment nommer un candidat tant qu'il n'a pas postulé : prénom + initiale,
// jamais le nom complet (MOBILE.md §4.3).
//
// Partagé plutôt que recopié dans chaque écran qui affiche une carte. La
// CVthèque, le deck et les matchs montrent le même objet ; s'ils le nommaient
// chacun à leur façon, il suffirait d'un oubli dans l'un des trois pour que
// le nom complet réapparaisse quelque part.

type Nameable = Pick<CandidateCard, 'first_name' | 'last_name_initial'>;

export function candidateDisplayName(candidate: Nameable): string {
  return candidate.last_name_initial
    ? `${candidate.first_name} ${candidate.last_name_initial}.`
    : candidate.first_name;
}

export function candidateInitials(candidate: Nameable): string {
  return `${candidate.first_name.charAt(0)}${candidate.last_name_initial ?? ''}`.toUpperCase();
}
