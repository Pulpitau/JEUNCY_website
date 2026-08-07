import type {
  ContractType,
  JobOfferStatus,
  PaymentStatus,
  WorkMode,
} from '@jeuncy/shared';
import { apiRequest } from './client';
import type { Skill } from './candidate-profile';

export interface JobOffer {
  id: number;
  company_id: number | null;
  cfa_organization_id: number | null;
  title: string;
  description: string;
  contract_type: ContractType;
  status: JobOfferStatus;
  payment_status: PaymentStatus;
  location: string | null;
  city: string | null;
  work_mode: WorkMode | null;
  compensation: string | null;
  experience_level: string | null;
  benefits: string | null;
  diploma_level: string | null;
  training_rhythm: string | null;
  skills: Skill[];
  published_at: string | null;
  expires_at: string | null;
  // Non-null des que l'acces aux candidatures de cette offre precise a ete
  // debloque (essai gratuit, paiement dedie, ou abonnement actif au moment de
  // la publication) — voir ApplicationService::listForOffer cote backend
  // pour la garde qui s'appuie dessus (ou sur un abonnement actif au moment
  // de la consultation, pas seulement de la publication).
  applications_unlocked_at: string | null;
  created_at: string;
  updated_at: string;
}

interface PublisherSummary {
  id: number;
  name: string;
  city: string | null;
  logo_url: string | null;
}

export interface PublicJobOffer extends JobOffer {
  company: PublisherSummary | null;
  cfa_organization: PublisherSummary | null;
}

export interface Paginated<T> {
  current_page: number;
  data: T[];
  last_page: number;
  per_page: number;
  total: number;
  next_page_url: string | null;
  prev_page_url: string | null;
}

export interface JobOfferInput {
  title: string;
  description: string;
  contract_type: ContractType;
  location?: string | null;
  city?: string | null;
  work_mode?: WorkMode | null;
  compensation?: string | null;
  experience_level?: string | null;
  benefits?: string | null;
  diploma_level?: string | null;
  training_rhythm?: string | null;
  skills?: string[];
}

export interface JobOfferSearchFilters {
  q?: string;
  contract_type?: ContractType;
  city?: string;
  work_mode?: WorkMode;
  page?: number;
}

// Tarifs de publication d'une offre SEULE (sans acces aux candidatures),
// differents entreprise/CFA (voir JobOfferService::priceLabelFor cote
// backend, source de verite pour le montant reellement facture) — nouveau
// modele economique du 2026-08-05, dernier ajustement de tarif le
// 2026-08-06, voir aussi lib/api/subscriptions.ts pour l'abonnement mensuel
// et APPLICATIONS_UNLOCK_PRICE_LABEL ci-dessous.
export const COMPANY_OFFER_PRICE_LABEL = '9,99 €';
export const CFA_OFFER_PRICE_LABEL = '5,99 €';

// Deblocage ponctuel de l'acces aux candidatures d'UNE offre precise, meme
// tarif entreprise/CFA.
export const APPLICATIONS_UNLOCK_PRICE_LABEL = '50 €';

export function offerPriceLabel(offer: Pick<JobOffer, 'cfa_organization_id'>): string {
  return offer.cfa_organization_id !== null
    ? CFA_OFFER_PRICE_LABEL
    : COMPANY_OFFER_PRICE_LABEL;
}

export function listMyOffers() {
  return apiRequest<JobOffer[]>('/job-offers');
}

export function createOffer(input: JobOfferInput) {
  return apiRequest<JobOffer>('/job-offers', { method: 'POST', body: input });
}

export function updateOffer(id: number, input: Partial<JobOfferInput>) {
  return apiRequest<JobOffer>(`/job-offers/${id}`, { method: 'PATCH', body: input });
}

export function archiveOffer(id: number) {
  return apiRequest<JobOffer>(`/job-offers/${id}/archive`, { method: 'POST' });
}

// Suppression definitive (contrairement a archiveOffer, irreversible) — voir
// JobOfferService::deleteForUser cote backend.
export function deleteOffer(id: number) {
  return apiRequest<{ deleted: true }>(`/job-offers/${id}`, { method: 'DELETE' });
}

export function createCheckoutSession(id: number) {
  return apiRequest<{ checkout_url: string }>(`/job-offers/${id}/checkout`, {
    method: 'POST',
  });
}

export function createApplicationsUnlockCheckoutSession(id: number) {
  return apiRequest<{ checkout_url: string }>(`/job-offers/${id}/checkout-applications`, {
    method: 'POST',
  });
}

export function publishOfferViaTrial(id: number) {
  return apiRequest<JobOffer>(`/job-offers/${id}/publish-trial`, { method: 'POST' });
}

export function publishOfferViaSubscription(id: number) {
  return apiRequest<JobOffer>(`/job-offers/${id}/publish-subscription`, {
    method: 'POST',
  });
}

export function searchPublicOffers(filters: JobOfferSearchFilters) {
  const params = new URLSearchParams();
  if (filters.q) params.set('q', filters.q);
  if (filters.contract_type) params.set('contract_type', filters.contract_type);
  if (filters.city) params.set('city', filters.city);
  if (filters.work_mode) params.set('work_mode', filters.work_mode);
  if (filters.page) params.set('page', String(filters.page));

  const query = params.toString();
  return apiRequest<Paginated<PublicJobOffer>>(
    `/job-offers/search${query ? `?${query}` : ''}`,
  );
}

export function getPublicOffer(id: number) {
  return apiRequest<PublicJobOffer>(`/job-offers/${id}`);
}
