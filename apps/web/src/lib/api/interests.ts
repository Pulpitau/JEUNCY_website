import type { InterestDecision } from '@jeuncy/shared';
import { apiRequest } from './client';

// « Ça m'intéresse » / « Passer », côté site.
//
// Mêmes routes que l'application (le serveur ne distingue pas les clients) et
// même projection volontairement partielle : `decision` est celle de
// l'APPELANT, jamais celle de l'autre partie. Un `matched_at` non nul dit
// tout ce qu'il y a à dire — c'est pourquoi il n'existe pas de champ
// « l'autre a dit non ».

export interface Interest {
  id: number;
  job_offer_id: number;
  candidate_profile_id: number;
  decision: InterestDecision;
  decided_at: string;
  /** Non nul = les deux ont dit oui. Un match n'est jamais annulable. */
  matched_at: string | null;
  application_id: number | null;
}

export interface InterestResult {
  interest: Interest;
  matched: boolean;
}

/**
 * Codes d'erreur traités nommément par les écrans. Chacun dit une chose
 * différente à l'utilisateur : un quota se réessaie demain, un âge minimum
 * ne se corrige pas du tout, une offre dépubliée n'est la faute de personne.
 */
export const INTEREST_ERRORS = {
  QUOTA: 'INTEREST_QUOTA_REACHED',
  ALREADY_DECIDED: 'INTEREST_ALREADY_DECIDED',
  CLOSED: 'INTEREST_CLOSED',
  OFFER_UNPUBLISHED: 'JOB_OFFER_NOT_PUBLISHED',
  PROFILE_REQUIRED: 'CANDIDATE_PROFILE_REQUIRED',
  BIRTH_DATE_REQUIRED: 'BIRTH_DATE_REQUIRED',
  MIN_AGE: 'MATCH_MIN_AGE',
  NOT_VERIFIED: 'COMPANY_NOT_VERIFIED',
} as const;

/** « Ça m'intéresse » côté candidat : le serveur prend toujours SON profil. */
export function likeOffer(jobOfferId: number) {
  return apiRequest<InterestResult>('/interests', {
    method: 'POST',
    body: { job_offer_id: jobOfferId },
  });
}

/** « Passer », par lot. Le site n'en envoie qu'un à la fois, mais la route est la même. */
export function passOffers(jobOfferIds: number[]) {
  return apiRequest<{ passed: number }>('/interests/batch', {
    method: 'POST',
    body: { job_offer_ids: jobOfferIds },
  });
}
