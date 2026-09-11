import type {
  ContractType,
  JobOfferStatus,
  PaymentStatus,
  WorkMode,
} from '@jeuncy/shared';

import type { CompensationPeriod } from '../format-compensation';
import { apiRequest } from './client';

// Types alignes sur apps/web/src/lib/api/job-offers.ts : meme API, memes
// colonnes. Seule la partie publique (recherche et detail) est portee ici ;
// la gestion des offres par une entreprise arrive en phase 3.

export interface Skill {
  id: number;
  name: string;
}

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
  /** Ancien champ texte libre, conserve pour les offres anterieures a la saisie structuree. */
  compensation: string | null;
  compensation_amount: number | null;
  compensation_period: CompensationPeriod | null;
  experience_level: string | null;
  benefits: string | null;
  diploma_level: string | null;
  training_rhythm: string | null;
  skills: Skill[];
  published_at: string | null;
  expires_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface PublisherSummary {
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
}

export interface JobOfferSearchFilters {
  q?: string;
  contract_type?: ContractType;
  city?: string;
  work_mode?: WorkMode;
  page?: number;
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

/** L'entreprise ou le CFA qui publie : exactement l'un des deux est renseigne. */
export function publisherOf(offer: PublicJobOffer): PublisherSummary | null {
  return offer.company ?? offer.cfa_organization;
}

export function isCfaOffer(offer: Pick<JobOffer, 'cfa_organization_id'>): boolean {
  return offer.cfa_organization_id !== null;
}
