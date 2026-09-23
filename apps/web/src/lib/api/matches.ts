import type { ApplicationStatus } from '@jeuncy/shared';
import { apiRequest } from './client';
import type { CandidateCard } from './cvtheque';
import type { CandidateProfile } from './candidate-profile';
import type { GeneratedCv } from './candidate-profile';
import type { PublicJobOffer } from './job-offers';

// Les matchs, des deux côtés. UNE route (`GET matches`), deux formes de
// réponse selon le rôle de l'appelant — c'est le serveur qui choisit
// (MatchService::presenterMatch), jamais un paramètre du client.
//
// Ce que chaque partie voit est une décision de produit (MOBILE.md §5) :
//   - le candidat a écrit son dossier, il n'a pas besoin de le relire : il ne
//     reçoit que son avancement ;
//   - l'employeur reçoit la carte d'exposition, et le dossier complet
//     seulement quand il est parti. Le match ouvre la conversation, il ne
//     livre ni le CV ni les coordonnées.

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
    /** Première réponse de l'employeur ; null tant qu'il se tait. */
    responded_at: string | null;
  } | null;
}

/** Le dossier tel que l'employeur le reçoit, avec ce qu'il n'avait pas avant. */
export interface MatchApplication {
  id: number;
  status: ApplicationStatus;
  cover_letter: string | null;
  contact_phone: string | null;
  cv_file_url: string | null;
  responded_at: string | null;
  created_at: string;
  candidate_profile: CandidateProfile & { user: { id: number; email: string } };
  generated_cv: GeneratedCv | null;
}

export interface EmployerMatch extends MatchBase {
  /** Réduit à l'essentiel : l'employeur connaît ses propres offres. */
  job_offer: { id: number; title: string } | null;
  candidate: CandidateCard | null;
  /** Null tant que le candidat n'a pas envoyé son dossier. */
  application: MatchApplication | null;
}

export type Match = CandidateMatch | EmployerMatch;

/**
 * Discriminant sur la réponse reçue, et non sur le rôle stocké côté client :
 * c'est la donnée qui dit ce qu'elle contient, et deux vues distinctes ne
 * doivent pas pouvoir diverger d'une session mal restaurée.
 */
export function isEmployerMatch(match: Match): match is EmployerMatch {
  return 'candidate' in match;
}

export function listMatches<T extends Match = Match>() {
  return apiRequest<T[]>('/matches');
}
