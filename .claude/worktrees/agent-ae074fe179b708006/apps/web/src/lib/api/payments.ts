import type { PaymentStatus } from '@jeuncy/shared';
import { apiRequest } from './client';
import type { JobOffer } from './job-offers';

export interface Payment {
  id: number;
  user_id: number;
  job_offer_id: number;
  amount_cents: number;
  currency: string;
  status: PaymentStatus;
  stripe_payment_intent_id: string | null;
  stripe_session_id: string | null;
  created_at: string;
  job_offer: JobOffer;
}

export function listMyPayments() {
  return apiRequest<Payment[]>('/payments/mine');
}
