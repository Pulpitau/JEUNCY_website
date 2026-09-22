export const MatchClosedReason = {
  OFFER_ARCHIVED: 'OFFER_ARCHIVED',
  OFFER_DELETED: 'OFFER_DELETED',
  OFFER_EXPIRED: 'OFFER_EXPIRED',
  APPLICATION_WITHDRAWN: 'APPLICATION_WITHDRAWN',
  ACCOUNT_DELETED: 'ACCOUNT_DELETED',
} as const;

export type MatchClosedReason =
  (typeof MatchClosedReason)[keyof typeof MatchClosedReason];
