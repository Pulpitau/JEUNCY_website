import type { SubscriptionStatus } from '@jeuncy/shared';
import { apiRequest } from './client';

export interface Subscription {
  id: number;
  user_id: number;
  status: SubscriptionStatus;
  amount_cents: number;
  currency: string;
  stripe_subscription_id: string;
  current_period_end: string | null;
  canceled_at: string | null;
  created_at: string;
}

// Tarifs mensuels, differents entreprise/CFA (voir
// SubscriptionService::priceCentsFor cote backend, source de verite) —
// publication illimitee + acces aux candidatures de toutes les offres inclus.
export const COMPANY_SUBSCRIPTION_PRICE_LABEL = '79 €';
export const CFA_SUBSCRIPTION_PRICE_LABEL = '99 €';

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
