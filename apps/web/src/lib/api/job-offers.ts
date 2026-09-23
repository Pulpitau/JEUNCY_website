import type { CompensationPeriod } from '@/lib/format-compensation';
import type {
  ContractType,
  JobOfferStatus,
  OfferSector,
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
  // Ancien champ texte libre, conserve en base pour ne rien perdre mais plus
  // ni saisi ni affiche : voir compensation_amount/compensation_period et
  // formatCompensation.
  compensation: string | null;
  compensation_amount: number | null;
  compensation_period: CompensationPeriod | null;
  experience_level: string | null;
  benefits: string | null;
  diploma_level: string | null;
  training_rhythm: string | null;
  // Colonnes du modele match (lot 1). `postal_code` commande l'entree dans
  // Decouvrir des deux cotes : sans lui l'offre n'est geocodee nulle part,
  // donc invisible de toute pile — c'est le sens de JOB_OFFER_NOT_LOCATED.
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
  compensation_amount?: number | null;
  compensation_period?: CompensationPeriod | null;
  experience_level?: string | null;
  benefits?: string | null;
  diploma_level?: string | null;
  training_rhythm?: string | null;
  postal_code?: string | null;
  sector?: OfferSector | null;
  recruitment_radius_km?: number;
  schedule?: string | null;
  start_date?: string | null;
  minimum_age?: number | null;
  requires_driving_license?: boolean;
  missions?: string[];
  skills?: string[];
}

export interface JobOfferSearchFilters {
  q?: string;
  contract_type?: ContractType;
  city?: string;
  work_mode?: WorkMode;
  page?: number;
}

// Tarifs de publication d'une offre SEULE, differents entreprise/CFA (voir
// JobOfferService::priceLabelFor cote backend, source de verite pour le
// montant reellement facture).
//
// Ce paiement ne couvre QUE la mise en ligne : ni l'acces aux candidatures, ni
// la CVtheque, qui passent exclusivement par l'abonnement depuis le
// 2026-08-17 (voir lib/api/subscriptions.ts). Le deblocage a l'offre qui
// existait entre le 2026-08-05 et cette date n'est plus vendable.
// Depuis le 2026-09-10 ce montant achete une PERIODE de mise en ligne, pas
// une publication definitive : passe ce delai l'offre sort de la ligne et
// son proprietaire la remet en ligne en repayant. Aucun prelevement
// automatique. La duree ci-dessous doit rester alignee sur
// services.stripe.offer_publication_days cote backend.
export const COMPANY_OFFER_PRICE_LABEL = '9,99 €';
export const CFA_OFFER_PRICE_LABEL = '5,99 €';
export const OFFER_PUBLICATION_DURATION_LABEL = '1 mois';

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

/**
 * L'offre en une minute (MOBILE.md §4.1) : le minimum pour qu'une offre
 * existe, soit publiee et entre dans « Decouvrir ». La description est
 * generee par le serveur et se complete ensuite depuis « Mes offres ».
 *
 * Le code postal est requis des ici, et pas seulement a la publication :
 * l'offre est publiee dans la foulee, et sans lui elle n'apparaitrait dans
 * aucune pile.
 */
export interface ExpressJobOfferInput {
  title: string;
  contract_type: ContractType;
  postal_code: string;
  city: string;
  sector: OfferSector;
  recruitment_radius_km?: number;
}

export function createExpressOffer(input: ExpressJobOfferInput) {
  return apiRequest<JobOffer>('/job-offers/express', { method: 'POST', body: input });
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

export function publishOfferViaTrial(id: number) {
  return apiRequest<JobOffer>(`/job-offers/${id}/publish-trial`, { method: 'POST' });
}

// Publication gratuite : le parcours normal depuis que Jeuncy ne facture
// plus les entreprises (2026-09-15). Les trois fonctions ci-dessus (paiement,
// essai, abonnement) restent pour le jour ou une grille tarifaire reviendrait.
export function publishOfferForFree(id: number) {
  return apiRequest<JobOffer>(`/job-offers/${id}/publish`, { method: 'POST' });
}

export function publishOfferViaSubscription(id: number) {
  return apiRequest<JobOffer>(`/job-offers/${id}/publish-subscription`, {
    method: 'POST',
  });
}

// Compteur d'offres en ligne (Jeuncy + partenaires), affiche en page d'accueil.
export function getPublicOfferCount() {
  return apiRequest<{ jeuncy: number; partenaires: number; total: number }>(
    '/job-offers/count',
  );
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
