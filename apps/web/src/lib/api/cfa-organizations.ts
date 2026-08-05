import type { WorkMode } from '@jeuncy/shared';
import { apiRequest } from './client';
import type { JobOffer, Paginated } from './job-offers';

export interface PublicCfaOrganization {
  id: number;
  name: string;
  siret: string | null;
  nda_number: string | null;
  qualiopi_number: string | null;
  description: string | null;
  diplomas_offered: string | null;
  diploma_level: string | null;
  training_mode: WorkMode | null;
  logo_url: string | null;
  website: string | null;
  address: string | null;
  city: string | null;
  postal_code: string | null;
}

export interface CfaSearchFilters {
  name?: string;
  city?: string;
  diploma_level?: string;
  training_mode?: WorkMode;
  page?: number;
}

export interface PublicCfaOrganizationWithOffers extends PublicCfaOrganization {
  job_offers: JobOffer[];
}

export function searchPublicCfaOrganizations(filters: CfaSearchFilters = {}) {
  const params = new URLSearchParams();
  if (filters.name) params.set('name', filters.name);
  if (filters.city) params.set('city', filters.city);
  if (filters.diploma_level) params.set('diploma_level', filters.diploma_level);
  if (filters.training_mode) params.set('training_mode', filters.training_mode);
  if (filters.page) params.set('page', String(filters.page));
  const query = params.toString();
  return apiRequest<Paginated<PublicCfaOrganization>>(
    `/cfa-organizations${query ? `?${query}` : ''}`,
  );
}

export function getPublicCfaOrganization(id: number) {
  return apiRequest<PublicCfaOrganizationWithOffers>(`/cfa-organizations/${id}`);
}
