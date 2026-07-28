import type { ApplicationStatus } from '@jeuncy/shared';
import { apiRequest } from './client';
import type { JobOffer } from './job-offers';
import type { GeneratedCv } from './candidate-profile';

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

export interface ApplicantSummary {
  id: number;
  user_id: number;
  first_name: string;
  last_name: string;
  city: string | null;
  photo_url: string | null;
  user: { id: number; email: string };
}

export interface ApplicationWithCandidate extends Application {
  candidate_profile: ApplicantSummary;
  generated_cv: GeneratedCv | null;
}

export interface ApplyToOfferInput {
  coverLetter?: string;
  contactPhone: string;
  // Exactement l'un des deux (jamais aucun, jamais les deux — voir
  // ApplyToOfferSection qui bascule entre les deux modes) : un CV deja genere
  // sur la plateforme, ou un fichier PDF importe pour cette candidature.
  generatedCvId?: number;
  cvFile?: File;
}

export function applyToOffer(jobOfferId: number, input: ApplyToOfferInput) {
  const formData = new FormData();
  formData.append('job_offer_id', String(jobOfferId));
  if (input.coverLetter) formData.append('cover_letter', input.coverLetter);
  formData.append('contact_phone', input.contactPhone);
  if (input.cvFile) {
    formData.append('cv_file', input.cvFile);
  } else if (input.generatedCvId) {
    formData.append('generated_cv_id', String(input.generatedCvId));
  }

  return apiRequest<Application>('/applications', {
    method: 'POST',
    body: formData,
  });
}

export function listMyApplications() {
  return apiRequest<ApplicationWithOffer[]>('/applications');
}

export function listApplicationsForOffer(jobOfferId: number) {
  return apiRequest<ApplicationWithCandidate[]>(`/job-offers/${jobOfferId}/applications`);
}

export function updateApplicationStatus(
  applicationId: number,
  status: ApplicationStatus,
) {
  return apiRequest<Application>(`/applications/${applicationId}/status`, {
    method: 'PATCH',
    body: { status },
  });
}
