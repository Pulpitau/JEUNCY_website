import { UserRole, type WorkMode } from '@jeuncy/shared';

import { toFormDataPart, type NativeFile } from './candidate-profile';
import { apiRequest } from './client';

// Types alignes sur apps/web/src/lib/api/company.ts et cfa-organization.ts.
// Une entreprise et un CFA ont deux tables et deux jeux de routes cote API
// (/company, /cfa-organization), mais l'application les traite ensemble : le
// role de l'utilisateur choisit la route, les ecrans restent les memes.

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
  /** Presence dans l'annuaire public des entreprises. */
  is_public: boolean;
  trial_started_at: string | null;
  trial_offers_count: number;
}

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
  is_public: boolean;
  trial_started_at: string | null;
  trial_offers_count: number;
}

export type Organization = Company | CfaOrganization;

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
  is_public?: boolean;
}

export type OrganizationRole = typeof UserRole.COMPANY | typeof UserRole.CFA;

export function isOrganizationRole(role: string | undefined): role is OrganizationRole {
  return role === UserRole.COMPANY || role === UserRole.CFA;
}

function basePath(role: OrganizationRole): string {
  return role === UserRole.COMPANY ? '/company' : '/cfa-organization';
}

export function getMyOrganization(role: OrganizationRole) {
  return apiRequest<Organization>(basePath(role));
}

export function createOrganization(
  role: OrganizationRole,
  input: CompanyInput | CfaOrganizationInput,
) {
  return apiRequest<Organization>(basePath(role), { method: 'POST', body: input });
}

export function updateOrganization(
  role: OrganizationRole,
  input: Partial<CompanyInput | CfaOrganizationInput>,
) {
  return apiRequest<Organization>(basePath(role), { method: 'PATCH', body: input });
}

/** jpeg/png/webp, 2 Mo max (UploadCompanyLogoRequest / UploadCfaOrganizationLogoRequest). */
export function uploadOrganizationLogo(role: OrganizationRole, file: NativeFile) {
  const formData = new FormData();
  formData.append('logo', toFormDataPart(file));

  return apiRequest<Organization>(`${basePath(role)}/logo`, {
    method: 'POST',
    body: formData,
  });
}

export function removeOrganizationLogo(role: OrganizationRole) {
  return apiRequest<Organization>(`${basePath(role)}/logo`, { method: 'DELETE' });
}
