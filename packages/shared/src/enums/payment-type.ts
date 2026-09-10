export const PaymentType = {
  OFFER_PUBLICATION: 'OFFER_PUBLICATION',
  APPLICATIONS_UNLOCK: 'APPLICATIONS_UNLOCK',
  // Prelevement mensuel d'abonnement. Synchronise a la main avec
  // App\Enums\PaymentType cote Laravel (voir CONVENTIONS.md).
  SUBSCRIPTION: 'SUBSCRIPTION',
} as const;

export type PaymentType = (typeof PaymentType)[keyof typeof PaymentType];
