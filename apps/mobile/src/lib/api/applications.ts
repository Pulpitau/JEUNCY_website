import type { ApplicationSource, ApplicationStatus } from '@jeuncy/shared';

import {
  toFormDataPart,
  type CandidateProfile,
  type GeneratedCv,
  type NativeFile,
} from './candidate-profile';
import { apiDownload, apiRequest } from './client';
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
  /** La ligne d'interet qui a precede le dossier, quand il vient d'un match. */
  interest_id: number | null;
  source: ApplicationSource;
  /** Premiere reponse de l'employeur (changement de statut), null tant qu'il se tait. */
  responded_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface ApplicationWithOffer extends Application {
  job_offer: JobOffer;
  generated_cv: GeneratedCv | null;
}

/**
 * Le dossier tel que l'employeur le recoit : c'est le seul endroit du
 * produit ou il voit le nom complet, le telephone, l'email et le CV. Avant
 * la candidature, il n'a que la carte d'exposition (MOBILE.md §4.3) — d'ou
 * deux types distincts plutot qu'un seul aux champs optionnels, qui aurait
 * laisse croire qu'un `phone` manquant est un profil incomplet.
 *
 * Aucune adresse de fichier (Application::forEmployer) : le CV se recupere
 * par downloadApplicationCv, garde a chaque appel.
 */
export interface ReceivedApplication extends Omit<Application, 'cv_file_url'> {
  candidate_profile: CandidateProfile & { user: { id: number; email: string } };
  has_cv: boolean;
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

// ---------------------------------------------------------------------------
// Cote employeur
// ---------------------------------------------------------------------------

/** Les dossiers recus sur une offre. 403 COMPANY_NOT_VERIFIED si non verifiee. */
export function listOfferApplications(jobOfferId: number) {
  return apiRequest<ReceivedApplication[]>(`/job-offers/${jobOfferId}/applications`);
}

/** Le PDF d'un dossier recu, servi en flux (jamais son adresse). */
export function downloadApplicationCv(applicationId: number) {
  return apiDownload(`/applications/${applicationId}/cv`);
}

/**
 * Statuts qu'un employeur peut poser. `SENT` en est absent a dessein : c'est
 * le statut initial, pose par le serveur, et la validation le refuse en
 * entree (UpdateApplicationStatusRequest).
 */
export type EmployerApplicationStatus = Exclude<ApplicationStatus, 'SENT'>;

export function updateApplicationStatus(
  applicationId: number,
  status: EmployerApplicationStatus,
) {
  return apiRequest<ReceivedApplication>(`/applications/${applicationId}/status`, {
    method: 'PATCH',
    body: { status },
  });
}
