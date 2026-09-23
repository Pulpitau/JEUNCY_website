import type { InterestDecision } from '@jeuncy/shared';

import { apiRequest } from './client';

// « Ca m'interesse » / « Passer » sur les offres Jeuncy, des deux cotes.
//
// La projection du serveur est volontairement partielle : `decision` est
// celle de l'APPELANT, jamais celle de l'autre partie. Un `matched_at` non
// nul dit tout ce qu'il y a a dire — c'est pour ca qu'il n'existe pas de
// champ « l'autre a dit non ».

export interface Interest {
  id: number;
  job_offer_id: number;
  candidate_profile_id: number;
  decision: InterestDecision;
  decided_at: string;
  /** Non nul = les deux ont dit oui. Un match n'est jamais annulable. */
  matched_at: string | null;
  /** Le dossier rattache au match, quand il est parti. */
  application_id: number | null;
}

export interface InterestResult {
  interest: Interest;
  matched: boolean;
}

/**
 * Codes d'erreur que les ecrans traitent nommement ; tout le reste tombe
 * dans le message generique de l'API. Ils viennent d'InterestService et de
 * DiscoverService, et disent chacun une chose differente a l'utilisateur :
 * un quota se reessaie demain, une offre non localisee se corrige tout de
 * suite, une entreprise non verifiee ne se corrige pas depuis cet ecran.
 */
export const INTEREST_ERRORS = {
  QUOTA: 'INTEREST_QUOTA_REACHED',
  ALREADY_DECIDED: 'INTEREST_ALREADY_DECIDED',
  CLOSED: 'INTEREST_CLOSED',
  CANDIDATE_GONE: 'CANDIDATE_NOT_ELIGIBLE',
  OFFER_UNPUBLISHED: 'JOB_OFFER_NOT_PUBLISHED',
  OFFER_NOT_LOCATED: 'JOB_OFFER_NOT_LOCATED',
  NOT_OPEN_HERE: 'MATCH_NOT_OPEN_HERE',
  NOT_VERIFIED: 'COMPANY_NOT_VERIFIED',
  BLOCKED: 'USER_BLOCKED',
  NOTHING_TO_UNDO: 'NOTHING_TO_UNDO',
  UNDO_EXPIRED: 'UNDO_WINDOW_EXPIRED',
  MATCH_NOTIFIED: 'MATCH_ALREADY_NOTIFIED',
  APPLICATION_ATTACHED: 'APPLICATION_ATTACHED',
} as const;

/** Lot maximal accepte par `interests/batch` (InterestService::LOT_MAX). */
export const PASS_BATCH_MAX = 50;

/** « Ca m'interesse » cote candidat : le serveur prend toujours SON profil. */
export function likeOffer(jobOfferId: number) {
  return apiRequest<InterestResult>('/interests', {
    method: 'POST',
    body: { job_offer_id: jobOfferId },
  });
}

/** « Ca m'interesse » cote employeur, sur une carte de la pile de cette offre. */
export function likeCandidate(jobOfferId: number, candidateProfileId: number) {
  return apiRequest<InterestResult>('/interests', {
    method: 'POST',
    body: { job_offer_id: jobOfferId, candidate_profile_id: candidateProfileId },
  });
}

/**
 * « Passer », par lot.
 *
 * Un PASS n'interesse personne d'autre que la pile : il n'a pas a partir
 * carte par carte pendant que le doigt enchaine. Les ecrans accumulent et
 * vident le lot quand la main s'arrete — ce qui rend aussi le geste
 * utilisable sur un reseau qui va et vient.
 */
export function passOffers(jobOfferIds: number[]) {
  return apiRequest<{ passed: number }>('/interests/batch', {
    method: 'POST',
    body: { job_offer_ids: jobOfferIds },
  });
}

export function passCandidates(jobOfferId: number, candidateProfileIds: number[]) {
  return apiRequest<{ passed: number }>('/interests/batch', {
    method: 'POST',
    body: { job_offer_id: jobOfferId, candidate_profile_ids: candidateProfileIds },
  });
}

export interface UndoResult {
  undone: { job_offer_id: number; candidate_profile_id: number };
}

/**
 * Annule le dernier geste. Refuse (409) sur un match deja annonce a l'autre
 * partie — en pratique, tous : sans file d'attente, l'annonce part dans
 * l'appel qui cree le match (MOBILE.md §5).
 */
export function undoLastInterest() {
  return apiRequest<UndoResult>('/interests/last', { method: 'DELETE' });
}
