import type { WorkMode } from '@jeuncy/shared';
import { apiRequest } from './client';

export interface CfaOrganization {
  id: number;
  user_id: number;
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
  trial_started_at: string | null;
  trial_offers_count: number;
}

export interface CfaOrganizationInput {
  name: string;
  siret?: string | null;
  nda_number?: string | null;
  qualiopi_number?: string | null;
  description?: string | null;
  diplomas_offered?: string | null;
  diploma_level?: string | null;
  training_mode?: WorkMode | null;
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

export function uploadCfaOrganizationLogo(file: File) {
  const formData = new FormData();
  formData.append('logo', file);

  return apiRequest<CfaOrganization>('/cfa-organization/logo', {
    method: 'POST',
    body: formData,
  });
}

export function removeCfaOrganizationLogo() {
  return apiRequest<CfaOrganization>('/cfa-organization/logo', { method: 'DELETE' });
}
