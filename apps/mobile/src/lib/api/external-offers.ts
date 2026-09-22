import type { WorkMode } from '@jeuncy/shared';

import { apiRequest } from './client';
import type { Paginated } from './job-offers';

// Offres importees de La bonne alternance, alignees sur
// apps/web/src/lib/api/external-job-offers.ts (partie publique seulement :
// l'audit admin reste sur le site). Servies a part des offres Jeuncy par
// l'API (ExternalJobOfferService::searchPublic, PUBLIC_COLUMNS) : pas de
// SIRET ni de NAF, ce sont des donnees de filtrage, pas de presentation.
//
// Ces offres ne se candidatent pas sur Jeuncy : `apply_url` mene au site de
// l'employeur (ou a La bonne alternance). Le mot « match » ne s'y applique
// jamais — le candidat « garde » une offre, il ne « matche » pas avec elle.

export const EXTERNAL_SOURCE_LABEL = 'La bonne alternance';

export interface ExternalJobOffer {
  id: number;
  source: 'lba';
  partner_label: string | null;
  title: string;
  description: string;
  /** Vide pour une partie des annonces relayees par France Travail. */
  company_name: string | null;
  company_size: string | null;
  company_website: string | null;
  company_naf_label: string | null;
  city: string | null;
  postal_code: string | null;
  /** Numero de departement : « 66 », « 2A », « 974 ». */
  department: string | null;
  work_mode: WorkMode | null;
  /** Date ISO (cast Eloquent `date`, serialise avec l'heure) : garder les 10 premiers caracteres. */
  contract_start: string | null;
  contract_duration_months: number | null;
  target_diploma_level: string | null;
  target_diploma_label: string | null;
  rome_codes: string[] | null;
  opening_count: number | null;
  apply_url: string;
  published_at: string | null;
  expires_at: string | null;
}

export interface ExternalOfferSearchFilters {
  department?: string;
  city?: string;
  work_mode?: WorkMode;
  q?: string;
  page?: number;
}

/** 12 offres par page, les plus recentes en premier (l'API trie). */
export function searchExternalOffers(filters: ExternalOfferSearchFilters) {
  const params = new URLSearchParams();
  if (filters.department) params.set('department', filters.department);
  if (filters.city) params.set('city', filters.city);
  if (filters.work_mode) params.set('work_mode', filters.work_mode);
  if (filters.q) params.set('q', filters.q);
  if (filters.page && filters.page > 1) params.set('page', String(filters.page));

  const query = params.toString();

  return apiRequest<Paginated<ExternalJobOffer>>(
    `/job-offers/external/search${query ? `?${query}` : ''}`,
  );
}

/** 404 EXTERNAL_JOB_OFFER_NOT_FOUND si l'offre a disparu de l'export ou a ete exclue. */
export function getExternalOffer(id: number) {
  return apiRequest<ExternalJobOffer>(`/job-offers/external/${id}`);
}

/** « Perpignan (66) », « Perpignan », « Departement 66 » (accentue a l'affichage) ou null selon l'export. */
export function formatExternalLocation(
  offer: Pick<ExternalJobOffer, 'city' | 'department'>,
): string | null {
  if (offer.city && offer.department) return `${offer.city} (${offer.department})`;
  if (offer.city) return offer.city;
  if (offer.department) return `Département ${offer.department}`;

  return null;
}
