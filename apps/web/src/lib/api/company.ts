import type { WorkMode } from '@jeuncy/shared';
import { apiRequest } from './client';

export interface Company {
  id: number;
  user_id: number;
  name: string;
  siret: string | null;
  description: string | null;
  logo_url: string | null;
  website: string | null;
  address: string | null;
  city: string | null;
  postal_code: string | null;
  work_mode: WorkMode | null;
  is_public: boolean;
  trial_started_at: string | null;
  trial_offers_count: number;
}

export interface CompanyInput {
  name: string;
  siret?: string | null;
  description?: string | null;
  website?: string | null;
  address?: string | null;
  city?: string | null;
  postal_code?: string | null;
  work_mode?: WorkMode | null;
  is_public?: boolean;
}

export function getMyCompany() {
  return apiRequest<Company>('/company');
}

export function createCompany(input: CompanyInput) {
  return apiRequest<Company>('/company', { method: 'POST', body: input });
}

export function updateCompany(input: Partial<CompanyInput>) {
  return apiRequest<Company>('/company', { method: 'PATCH', body: input });
}

export function uploadCompanyLogo(file: File) {
  const formData = new FormData();
  formData.append('logo', file);

  return apiRequest<Company>('/company/logo', { method: 'POST', body: formData });
}

export function removeCompanyLogo() {
  return apiRequest<Company>('/company/logo', { method: 'DELETE' });
}
