import { apiRequest } from './client';

// Signalement et blocage (MOBILE.md §7).
//
// LA CIBLE SE DESIGNE SANS `user_id`, des deux cotes. Ni la carte candidat
// (liste blanche du presenteur) ni la fiche d'une organisation n'exposent
// l'identifiant du compte d'en face — c'est exactement l'invariant du lot 1.
// Un client qui devrait inventer cet identifiant finirait par signaler
// quelqu'un au hasard, alors on designe ce qu'on a sous les yeux : un profil,
// une offre, ou la ligne de match.

/** D'ou part le signalement. */
export type ReportContext = 'CARD' | 'MATCH' | 'PHOTO' | 'OFFER';

/** Exactement l'une des trois cibles, jamais deux. */
export interface ModerationTarget {
  candidate_profile_id?: number;
  job_offer_id?: number;
  /** Seulement en contexte MATCH. */
  offer_interest_id?: number;
}

export interface Report {
  id: number;
  context: ReportContext;
  reason: string;
  details: string | null;
  created_at: string;
}

export interface UserBlock {
  id: number;
  blocker_user_id: number;
  blocked_user_id: number;
  created_at: string;
}

/**
 * Motifs proposes.
 *
 * Volontairement courts et concrets : une liste de categories juridiques
 * (« contenu inapproprie ») ne dit rien a l'equipe qui traite, et rien non
 * plus a un jeune de seize ans qui vient de lire quelque chose de deplace.
 * Le champ libre reste la pour le reste.
 */
export const REPORT_REASONS = [
  { value: 'propos-deplaces', label: 'Propos déplacés ou insultants' },
  { value: 'contenu-choquant', label: 'Contenu choquant ou violent' },
  { value: 'fausse-annonce', label: 'Fausse annonce ou arnaque' },
  { value: 'demande-argent', label: "On me demande de l'argent" },
  { value: 'coordonnees', label: 'On me demande mes coordonnées personnelles' },
  { value: 'autre', label: 'Autre' },
] as const;

export function reportTarget(input: {
  context: ReportContext;
  reason: string;
  details?: string;
  target: ModerationTarget;
}) {
  return apiRequest<Report>('/reports', {
    method: 'POST',
    body: {
      context: input.context,
      reason: input.reason,
      details: input.details?.trim() || undefined,
      ...input.target,
    },
  });
}

/** Le blocage est mutuel : les deux disparaissent des piles de l'autre. */
export function blockTarget(target: ModerationTarget) {
  return apiRequest<UserBlock>('/blocks', { method: 'POST', body: target });
}

export function unblock(blockId: number) {
  return apiRequest<{ deleted: true }>(`/blocks/${blockId}`, { method: 'DELETE' });
}

/** Codes traites nommement par la feuille de signalement. */
export const MODERATION_ERRORS = {
  ALREADY_SENT: 'REPORT_ALREADY_SENT',
  CANNOT_REPORT_SELF: 'CANNOT_REPORT_SELF',
} as const;
