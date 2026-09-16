import type { WorkMode } from '@jeuncy/shared';
import { apiRequest } from './client';
import type { Paginated } from './job-offers';

// Offres importees de La bonne alternance (voir ExternalJobOfferService cote
// API). Servies a part des offres Jeuncy : la page /offres les affiche apres.

export const EXTERNAL_SOURCE_LABEL = 'La bonne alternance';

export interface ExternalJobOffer {
  id: number;
  source: 'lba';
  partner_label: string | null;
  title: string;
  description: string;
  company_name: string | null;
  company_size: string | null;
  company_website: string | null;
  company_naf_label: string | null;
  city: string | null;
  postal_code: string | null;
  department: string | null;
  work_mode: WorkMode | null;
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

export interface ExternalJobOfferSearchFilters {
  q?: string;
  contract_type?: string;
  city?: string;
  work_mode?: WorkMode;
  department?: string;
  page?: number;
}

export function searchExternalOffers(filters: ExternalJobOfferSearchFilters) {
  const params = new URLSearchParams();
  if (filters.q) params.set('q', filters.q);
  if (filters.contract_type) params.set('contract_type', filters.contract_type);
  if (filters.city) params.set('city', filters.city);
  if (filters.work_mode) params.set('work_mode', filters.work_mode);
  if (filters.department) params.set('department', filters.department);
  if (filters.page && filters.page > 1) params.set('page', String(filters.page));
  const query = params.toString();

  return apiRequest<Paginated<ExternalJobOffer>>(
    `/job-offers/external/search${query ? `?${query}` : ''}`,
  );
}

export function getExternalOffer(id: number) {
  return apiRequest<ExternalJobOffer>(`/job-offers/external/${id}`);
}

// ---- Administration : audit du filtre des ecoles ----------------------

export interface ExternalJobOfferAdmin extends ExternalJobOffer {
  company_siret: string | null;
  company_naf: string | null;
  status: 'ACTIVE' | 'EXCLUDED';
  exclusion_reason: string | null;
  is_delegated: boolean;
}

export interface ExternalImportReport {
  date: string;
  mesure_seulement: boolean;
  octets: number | null;
  lus: number;
  inexploitables: number;
  hors_perimetre: number;
  recruteurs_ignores: number;
  inactives: number;
  retenues: number;
  actives: number;
  exclues: number;
  exclues_par_raison: Record<string, number>;
  exclues_exemples: {
    employeur: string | null;
    titre: string;
    departement: string;
    raison: string;
  }[];
  par_departement: Record<string, number>;
  supprimees: number;
  duree_s: number;
}

export interface ExternalOffersStats {
  actives: number;
  exclues: number;
  exclues_par_raison: Record<string, number>;
  employeurs_bloques: number;
  dernier_import: ExternalImportReport | null;
  departements: string[];
}

export interface ExternalEmployerBlock {
  id: number;
  siret: string | null;
  normalized_name: string | null;
  display_name: string;
  reason: string | null;
  created_at: string;
}

export function getExternalOffersStats() {
  return apiRequest<ExternalOffersStats>('/admin/external-job-offers/stats');
}

export function listExternalOffersAsAdmin(filters: {
  status?: 'ACTIVE' | 'EXCLUDED';
  q?: string;
  page?: number;
}) {
  const params = new URLSearchParams();
  if (filters.status) params.set('status', filters.status);
  if (filters.q) params.set('q', filters.q);
  if (filters.page && filters.page > 1) params.set('page', String(filters.page));
  const query = params.toString();

  return apiRequest<Paginated<ExternalJobOfferAdmin>>(
    `/admin/external-job-offers${query ? `?${query}` : ''}`,
  );
}

export function blockExternalEmployer(offerId: number, reason?: string) {
  return apiRequest<ExternalEmployerBlock & { offres_retirees: number }>(
    `/admin/external-job-offers/${offerId}/block-employer`,
    { method: 'POST', body: reason ? { reason } : {} },
  );
}

export function listExternalEmployerBlocks() {
  return apiRequest<ExternalEmployerBlock[]>('/admin/external-employer-blocks');
}

export function removeExternalEmployerBlock(id: number) {
  return apiRequest<{ deleted: true }>(`/admin/external-employer-blocks/${id}`, {
    method: 'DELETE',
  });
}
