import type { ApplicationStatus } from '@jeuncy/shared';

import type { ReceivedApplication } from './applications';
import { apiRequest } from './client';
import type { CandidateCard } from './discover';
import type { PublicJobOffer } from './job-offers';

// Les matchs, des deux cotes. Une seule route (`GET matches`), deux formes
// de reponse selon le role de l'appelant — c'est MatchService::presenterMatch
// qui choisit, pas un parametre.
//
// Ce que chaque partie voit est une decision de produit, pas une commodite
// d'API (MOBILE.md §5) :
//   - le candidat a ecrit son dossier, il n'a pas besoin de le relire : il
//     ne recoit que son avancement ;
//   - l'employeur ne recoit la carte d'exposition que tant que le dossier
//     n'est pas parti. Le match ouvre la conversation, il ne livre ni le CV
//     ni les coordonnees.

/** `AWAITING_APPLICATION` tant que le dossier n'est pas parti, puis `APPLICATION_SENT`. */
export type MatchStatus = 'AWAITING_APPLICATION' | 'APPLICATION_SENT';

interface MatchBase {
  id: number;
  matched_at: string;
  status: MatchStatus;
}

export interface CandidateMatch extends MatchBase {
  job_offer: PublicJobOffer;
  application: {
    id: number;
    status: ApplicationStatus;
    /** Premiere reponse de l'employeur ; nourrit le badge « Repond en N jours ». */
    responded_at: string | null;
  } | null;
}

export interface EmployerMatch extends MatchBase {
  /** Reduit a l'essentiel : l'employeur connait ses propres offres. */
  job_offer: { id: number; title: string } | null;
  candidate: CandidateCard | null;
  /** Null tant que le candidat n'a pas envoye son dossier. */
  application: ReceivedApplication | null;
}

export type Match = CandidateMatch | EmployerMatch;

/**
 * Discriminant sur `candidate` plutot que sur le role stocke cote client :
 * c'est la reponse recue qui dit ce qu'elle contient, et deux ecrans
 * distincts ne doivent pas pouvoir diverger d'une session mal restauree.
 */
export function isEmployerMatch(match: Match): match is EmployerMatch {
  return 'candidate' in match;
}

export function listMatches<T extends Match = Match>() {
  return apiRequest<T[]>('/matches');
}

export function getMatch<T extends Match = Match>(id: number) {
  return apiRequest<T>(`/matches/${id}`);
}
