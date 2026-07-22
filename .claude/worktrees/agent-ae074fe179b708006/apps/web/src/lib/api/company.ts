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
}

export interface CompanyInput {
  name: string;
  siret?: string | null;
  description?: string | null;
  website?: string | null;
  address?: string | null;
  city?: string | null;
  postal_code?: string | null;
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
