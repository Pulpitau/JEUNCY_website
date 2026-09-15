import type { ApplicationStatus } from '@jeuncy/shared';

import { toFormDataPart, type GeneratedCv, type NativeFile } from './candidate-profile';
import { apiRequest } from './client';
import type { JobOffer } from './job-offers';

// Types alignes sur apps/web/src/lib/api/applications.ts.

export interface Application {
  id: number;
  candidate_profile_id: number;
  job_offer_id: number;
  status: ApplicationStatus;
  cover_letter: string | null;
  contact_phone: string | null;
  generated_cv_id: number | null;
  cv_file_url: string | null;
  created_at: string;
  updated_at: string;
}

export interface ApplicationWithOffer extends Application {
  job_offer: JobOffer;
  generated_cv: GeneratedCv | null;
}

export interface ApplyToOfferInput {
  coverLetter?: string;
  contactPhone: string;
  // Exactement l'un des deux (StoreApplicationRequest : required_without) :
  // un CV deja genere sur la plateforme, ou un PDF joint a cette candidature.
  generatedCvId?: number;
  cvFile?: NativeFile;
}

export function applyToOffer(jobOfferId: number, input: ApplyToOfferInput) {
  const formData = new FormData();
  formData.append('job_offer_id', String(jobOfferId));
  formData.append('contact_phone', input.contactPhone);
  if (input.coverLetter) formData.append('cover_letter', input.coverLetter);
  if (input.cvFile) {
    formData.append('cv_file', toFormDataPart(input.cvFile));
  } else if (input.generatedCvId) {
    formData.append('generated_cv_id', String(input.generatedCvId));
  }

  return apiRequest<Application>('/applications', { method: 'POST', body: formData });
}

export function listMyApplications() {
  return apiRequest<ApplicationWithOffer[]>('/applications');
}

// Retrait definitif par le candidat (ApplicationService::withdrawForUser).
export function withdrawApplication(applicationId: number) {
  return apiRequest<{ withdrawn: true }>(`/applications/${applicationId}`, {
    method: 'DELETE',
  });
}
