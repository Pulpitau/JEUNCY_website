export const PaymentStatus = {
  PENDING: 'PENDING',
  SUCCEEDED: 'SUCCEEDED',
  FAILED: 'FAILED',
  REFUNDED: 'REFUNDED',
  // Uniquement sur JobOffer.payment_status, jamais Payment.status (aucune
  // transaction Stripe reelle pour une offre publiee via l'essai gratuit).
  TRIAL: 'TRIAL',
  // Idem TRIAL mais pour une offre publiee gratuitement via un abonnement actif.
  SUBSCRIPTION: 'SUBSCRIPTION',
  // Offre publiee gratuitement : Jeuncy est gratuit pour les entreprises
  // depuis le 2026-09-15 (voir config services.jeuncy cote API).
  FREE: 'FREE',
} as const;

export type PaymentStatus = (typeof PaymentStatus)[keyof typeof PaymentStatus];
