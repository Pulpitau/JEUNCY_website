export const JobOfferStatus = {
  DRAFT: 'DRAFT',
  PUBLISHED: 'PUBLISHED',
  EXPIRED: 'EXPIRED',
  ARCHIVED: 'ARCHIVED',
} as const;

export type JobOfferStatus = (typeof JobOfferStatus)[keyof typeof JobOfferStatus];
