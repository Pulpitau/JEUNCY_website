import type { SubscriptionStatus } from '@jeuncy/shared';
import { apiRequest } from './client';

export interface Subscription {
  id: number;
  user_id: number;
  status: SubscriptionStatus;
  amount_cents: number;
  currency: string;
  stripe_subscription_id: string;
  is_founder_rate: boolean;
  current_period_end: string | null;
  canceled_at: string | null;
  created_at: string;
}

// Etat de l'offre d'ouverture, servi par un endpoint public : le compteur de
// places doit s'afficher pour un visiteur sans compte, c'est tout l'interet de
// l'argument.
export interface FounderOffer {
  price_cents: number;
  standard_company_price_cents: number;
  standard_cfa_price_cents: number;
  seats_total: number;
  seats_taken: number;
  seats_remaining: number;
  available: boolean;
}

// Tarif mensuel plein, identique entreprise et CFA depuis le 2026-08-17 (voir
// SubscriptionService::standardPriceCentsFor cote backend, source de verite) :
// offres illimitees + candidatures + CVtheque.
export const SUBSCRIPTION_PRICE_LABEL = '499 €';
export const FOUNDER_SUBSCRIPTION_PRICE_LABEL = '299 €';

export function getFounderOffer() {
  return apiRequest<FounderOffer>('/subscriptions/founder-offer');
}

export function createSubscriptionCheckoutSession() {
  return apiRequest<{ checkout_url: string }>('/subscriptions/checkout', {
    method: 'POST',
  });
}

export function getMySubscription() {
  return apiRequest<Subscription | null>('/subscriptions/mine');
}

export function cancelSubscription() {
  return apiRequest<Subscription>('/subscriptions/cancel', { method: 'POST' });
}
