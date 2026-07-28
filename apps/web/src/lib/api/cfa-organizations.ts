import { apiRequest } from './client';
import type { JobOffer, Paginated } from './job-offers';

export interface PublicCfaOrganization {
  id: number;
  name: string;
  description: string | null;
  logo_url: string | null;
  website: string | null;
  address: string | null;
  city: string | null;
  postal_code: string | null;
}

export interface PublicCfaOrganizationWithOffers extends PublicCfaOrganization {
  job_offers: JobOffer[];
}

export function searchPublicCfaOrganizations(city?: string) {
  const params = new URLSearchParams();
  if (city) params.set('city', city);
  const query = params.toString();
  return apiRequest<Paginated<PublicCfaOrganization>>(
    `/cfa-organizations${query ? `?${query}` : ''}`,
  );
}

export function getPublicCfaOrganization(id: number) {
  return apiRequest<PublicCfaOrganizationWithOffers>(`/cfa-organizations/${id}`);
}
