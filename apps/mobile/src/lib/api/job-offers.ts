import type {
  ContractType,
  JobOfferStatus,
  OfferSector,
  PaymentStatus,
  WorkMode,
} from '@jeuncy/shared';

import type { CompensationPeriod } from '../format-compensation';
import { apiRequest } from './client';

// Types alignes sur apps/web/src/lib/api/job-offers.ts : meme API, memes
// colonnes. La partie publique (recherche et detail) sert au candidat, la
// partie authentifiee en bas de fichier a l'entreprise et au CFA.

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
  // Colonnes du modele match (lot 1). `postal_code` commande l'entree dans
  // les deux piles : sans lui l'offre n'est geocodee nulle part, donc
  // invisible de Decouvrir — c'est le sens de JOB_OFFER_NOT_LOCATED.
  postal_code: string | null;
  sector: OfferSector | null;
  /** Rayon de recrutement en km (defaut 30 en base, jamais null). */
  recruitment_radius_km: number;
  schedule: string | null;
  start_date: string | null;
  /** 16 a 18 ; le deck employeur exige max(16, minimum_age). */
  minimum_age: number | null;
  requires_driving_license: boolean;
  missions: string[] | null;
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

// ---------------------------------------------------------------------------
// Cote entreprise et CFA
// ---------------------------------------------------------------------------

/** Une offre publiee, localisee : la seule qui puisse porter une pile. */
export function canDiscoverFrom(offer: JobOffer): boolean {
  return offer.status === 'PUBLISHED' && offer.postal_code !== null;
}

export function listMyOffers() {
  return apiRequest<JobOffer[]>('/job-offers');
}

export interface ExpressJobOfferInput {
  title: string;
  contract_type: ContractType;
  /** Cinq chiffres. Requis des l'express : l'offre est publiee dans la foulee. */
  postal_code: string;
  city: string;
  sector: OfferSector;
  recruitment_radius_km?: number;
}

/**
 * L'offre en une minute (MOBILE.md §4.1) : de quoi entrer dans Decouvrir
 * tout de suite, le reste se complete ensuite depuis « Mes offres ». Le
 * serveur la cree ET la publie — rien a appeler apres.
 */
export function createExpressOffer(input: ExpressJobOfferInput) {
  return apiRequest<JobOffer>('/job-offers/express', { method: 'POST', body: input });
}

export interface JobOfferInput {
  title: string;
  description: string;
  contract_type: ContractType;
  city?: string | null;
  postal_code?: string | null;
  sector?: OfferSector | null;
  recruitment_radius_km?: number;
  work_mode?: WorkMode | null;
  schedule?: string | null;
  start_date?: string | null;
  minimum_age?: number | null;
  requires_driving_license?: boolean;
  missions?: string[];
}

export function updateOffer(id: number, input: Partial<JobOfferInput>) {
  return apiRequest<JobOffer>(`/job-offers/${id}`, { method: 'PATCH', body: input });
}

/** Publication gratuite, le parcours normal depuis le 2026-09-15. */
export function publishOffer(id: number) {
  return apiRequest<JobOffer>(`/job-offers/${id}/publish`, { method: 'POST' });
}

export function archiveOffer(id: number) {
  return apiRequest<JobOffer>(`/job-offers/${id}/archive`, { method: 'POST' });
}
