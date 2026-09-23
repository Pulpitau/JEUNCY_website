export const MatchClosedReason = {
  OFFER_ARCHIVED: 'OFFER_ARCHIVED',
  OFFER_DELETED: 'OFFER_DELETED',
  OFFER_EXPIRED: 'OFFER_EXPIRED',
  APPLICATION_WITHDRAWN: 'APPLICATION_WITHDRAWN',
  ACCOUNT_DELETED: 'ACCOUNT_DELETED',
  /** Personne n'a répondu dans le délai (relances, lot 4). */
  EXPIRED: 'EXPIRED',
  /** Clôture par l'équipe après trente jours de silence de l'employeur. */
  CLOSED_BY_STAFF: 'CLOSED_BY_STAFF',
} as const;

export type MatchClosedReason =
  (typeof MatchClosedReason)[keyof typeof MatchClosedReason];
