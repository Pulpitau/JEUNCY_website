import type { VerificationStatus, WorkMode } from '@jeuncy/shared';
import { apiRequest } from './client';

export interface Company {
  id: number;
  user_id: number;
  name: string;
  siret: string | null;
  // Vérification de l'employeur (MOBILE.md §4.0). Le statut est public (signal
  // de confiance montré au candidat) ; la note, elle, n'est servie qu'au
  // propriétaire et à l'admin — d'où son absence sur la fiche publique.
  verification_status: VerificationStatus;
  verification_note?: string | null;
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
  // Requis depuis le lot 1 : c'est lui qui déclenche la vérification. Le
  // serveur répond INVALID_INPUT sans lui, à la création comme à la
  // modification.
  siret: string;
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
