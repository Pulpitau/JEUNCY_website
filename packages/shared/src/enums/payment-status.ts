export const PaymentStatus = {
  PENDING: 'PENDING',
  SUCCEEDED: 'SUCCEEDED',
  FAILED: 'FAILED',
  REFUNDED: 'REFUNDED',
  // Uniquement sur JobOffer.payment_status, jamais Payment.status (aucune
  // transaction Stripe reelle pour une offre publiee via l'essai gratuit).
  TRIAL: 'TRIAL',
} as const;

export type PaymentStatus = (typeof PaymentStatus)[keyof typeof PaymentStatus];
