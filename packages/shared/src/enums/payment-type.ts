export const PaymentType = {
  OFFER_PUBLICATION: 'OFFER_PUBLICATION',
  APPLICATIONS_UNLOCK: 'APPLICATIONS_UNLOCK',
} as const;

export type PaymentType = (typeof PaymentType)[keyof typeof PaymentType];
