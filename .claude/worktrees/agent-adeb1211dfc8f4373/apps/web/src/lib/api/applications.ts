import type { ApplicationStatus } from '@jeuncy/shared';
import { apiRequest } from './client';
import type { JobOffer } from './job-offers';

export interface Application {
  id: number;
  candidate_profile_id: number;
  job_offer_id: number;
  status: ApplicationStatus;
  cover_letter: string | null;
  created_at: string;
  updated_at: string;
}

export interface ApplicationWithOffer extends Application {
  job_offer: JobOffer;
}

export interface ApplicantSummary {
  id: number;
  user_id: number;
  first_name: string;
  last_name: string;
  city: string | null;
  photo_url: string | null;
}

export interface ApplicationWithCandidate extends Application {
  candidate_profile: ApplicantSummary;
}

export function applyToOffer(jobOfferId: number, coverLetter?: string) {
  return apiRequest<Application>('/applications', {
    method: 'POST',
    body: { job_offer_id: jobOfferId, cover_letter: coverLetter || undefined },
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
