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
}

export interface PublicCompanyWithOffers extends PublicCompany {
  job_offers: JobOffer[];
}

export function searchPublicCompanies(city?: string) {
  const params = new URLSearchParams();
  if (city) params.set('city', city);
  const query = params.toString();
  return apiRequest<Paginated<PublicCompany>>(`/companies${query ? `?${query}` : ''}`);
}

export function getPublicCompany(id: number) {
  return apiRequest<PublicCompanyWithOffers>(`/companies/${id}`);
}
