import { apiRequest } from './client';

export interface CfaOrganization {
  id: number;
  user_id: number;
  name: string;
  description: string | null;
  logo_url: string | null;
  website: string | null;
  address: string | null;
  city: string | null;
  postal_code: string | null;
}

export interface CfaOrganizationInput {
  name: string;
  description?: string | null;
  website?: string | null;
  address?: string | null;
  city?: string | null;
  postal_code?: string | null;
}

export function getMyCfaOrganization() {
  return apiRequest<CfaOrganization>('/cfa-organization');
}

export function createCfaOrganization(input: CfaOrganizationInput) {
  return apiRequest<CfaOrganization>('/cfa-organization', {
    method: 'POST',
    body: input,
  });
}

export function updateCfaOrganization(input: Partial<CfaOrganizationInput>) {
  return apiRequest<CfaOrganization>('/cfa-organization', {
    method: 'PATCH',
    body: input,
  });
}
