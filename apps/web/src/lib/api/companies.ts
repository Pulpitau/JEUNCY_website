import type { ContractType, WorkMode } from '@jeuncy/shared';
import { apiRequest } from './client';
import type { JobOffer, Paginated } from './job-offers';

export interface PublicCompany {
  id: number;
  name: string;
  siret: string | null;
  description: string | null;
  logo_url: string | null;
  website: string | null;
  address: string | null;
  city: string | null;
  postal_code: string | null;
  work_mode: WorkMode | null;
}

export interface CompanySearchFilters {
  name?: string;
  city?: string;
  contract_type?: ContractType;
  work_mode?: WorkMode;
  page?: number;
}

export interface PublicCompanyWithOffers extends PublicCompany {
  job_offers: JobOffer[];
}

export function searchPublicCompanies(filters: CompanySearchFilters = {}) {
  const params = new URLSearchParams();
  if (filters.name) params.set('name', filters.name);
  if (filters.city) params.set('city', filters.city);
  if (filters.contract_type) params.set('contract_type', filters.contract_type);
  if (filters.work_mode) params.set('work_mode', filters.work_mode);
  if (filters.page) params.set('page', String(filters.page));
  const query = params.toString();
  return apiRequest<Paginated<PublicCompany>>(`/companies${query ? `?${query}` : ''}`);
}

export function getPublicCompany(id: number) {
  return apiRequest<PublicCompanyWithOffers>(`/companies/${id}`);
}
