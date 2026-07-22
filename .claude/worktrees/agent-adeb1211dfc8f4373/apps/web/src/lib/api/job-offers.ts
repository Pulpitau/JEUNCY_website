import type { ContractType, JobOfferStatus, PaymentStatus } from '@jeuncy/shared';
import { apiRequest } from './client';

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
  published_at: string | null;
  expires_at: string | null;
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
}

export interface JobOfferSearchFilters {
  q?: string;
  contract_type?: ContractType;
  city?: string;
  page?: number;
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

export function createCheckoutSession(id: number) {
  return apiRequest<{ checkout_url: string }>(`/job-offers/${id}/checkout`, {
    method: 'POST',
  });
}

export function searchPublicOffers(filters: JobOfferSearchFilters) {
  const params = new URLSearchParams();
  if (filters.q) params.set('q', filters.q);
  if (filters.contract_type) params.set('contract_type', filters.contract_type);
  if (filters.city) params.set('city', filters.city);
  if (filters.page) params.set('page', String(filters.page));

  const query = params.toString();
  return apiRequest<Paginated<PublicJobOffer>>(
    `/job-offers/search${query ? `?${query}` : ''}`,
  );
}

export function getPublicOffer(id: number) {
  return apiRequest<PublicJobOffer>(`/job-offers/${id}`);
}
