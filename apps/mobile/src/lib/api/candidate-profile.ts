import { apiRequest } from './client';

// Types alignes sur apps/web/src/lib/api/candidate-profile.ts : meme API,
// memes colonnes. Les regles de validation (champs obligatoires, formats,
// tailles) vivent cote serveur dans app/Http/Requests/CandidateProfile/ ;
// les schemas Zod des ecrans les repetent pour donner un message immediat,
// mais c'est le serveur qui tranche.

export interface Experience {
  id: number;
  candidate_profile_id: number;
  title: string;
  company: string;
  location: string | null;
  start_date: string;
  end_date: string | null;
  description: string | null;
}

export interface Education {
  id: number;
  candidate_profile_id: number;
  degree: string;
  school: string;
  field_of_study: string | null;
  start_date: string;
  end_date: string | null;
}

export interface Skill {
  id: number;
  name: string;
}

export interface Language {
  id: number;
  candidate_profile_id: number;
  name: string;
  level: string;
}

export interface Software {
  id: number;
  name: string;
}

export interface CandidateProfile {
  id: number;
  user_id: number;
  first_name: string;
  last_name: string;
  headline: string | null;
  phone: string | null;
  /** Obligatoire depuis le 2026-09-11, 15 ans minimum (StoreCandidateProfileRequest). */
  birth_date: string | null;
  address: string | null;
  city: string | null;
  postal_code: string | null;
  bio: string | null;
  hobbies: string | null;
  driving_license: string | null;
  video_url: string | null;
  portfolio_url: string | null;
  linkedin_url: string | null;
  photo_url: string | null;
  /** Droit d'opposition a la CVtheque (RGPD art. 21). */
  is_visible_in_cvtheque: boolean;
  cv_file_url: string | null;
  cv_original_filename: string | null;
  cv_uploaded_at: string | null;
  experiences: Experience[];
  educations: Education[];
  skills: Skill[];
  languages: Language[];
  software: Software[];
}

export interface GeneratedCv {
  id: number;
  candidate_profile_id: number;
  file_url: string;
  archived_at: string | null;
  generated_at: string;
}

export interface CandidateProfileInput {
  first_name: string;
  last_name: string;
  birth_date: string;
  headline?: string | null;
  phone?: string | null;
  address?: string | null;
  city?: string | null;
  postal_code?: string | null;
  bio?: string | null;
  hobbies?: string | null;
  driving_license?: string | null;
  video_url?: string | null;
  portfolio_url?: string | null;
  linkedin_url?: string | null;
}

export interface ExperienceInput {
  title: string;
  company: string;
  location?: string | null;
  start_date: string;
  end_date?: string | null;
  description?: string | null;
}

export interface EducationInput {
  degree: string;
  school: string;
  field_of_study?: string | null;
  start_date: string;
  end_date?: string | null;
}

export interface LanguageInput {
  name: string;
  level: string;
}

// Fichier a envoyer en multipart depuis React Native. Contrairement au
// navigateur, il n'y a pas d'objet File : FormData accepte un objet
// { uri, name, type } que la couche native lit directement sur le disque.
export interface NativeFile {
  uri: string;
  name: string;
  type: string;
}

export function getMyProfile() {
  return apiRequest<CandidateProfile>('/candidate-profile');
}

export function createProfile(input: CandidateProfileInput) {
  return apiRequest<CandidateProfile>('/candidate-profile', {
    method: 'POST',
    body: input,
  });
}

export function updateProfile(
  input: Partial<CandidateProfileInput> & { is_visible_in_cvtheque?: boolean },
) {
  return apiRequest<CandidateProfile>('/candidate-profile', {
    method: 'PATCH',
    body: input,
  });
}

export function addExperience(input: ExperienceInput) {
  return apiRequest<Experience>('/candidate-profile/experiences', {
    method: 'POST',
    body: input,
  });
}

export function updateExperience(id: number, input: ExperienceInput) {
  return apiRequest<Experience>(`/candidate-profile/experiences/${id}`, {
    method: 'PATCH',
    body: input,
  });
}

export function deleteExperience(id: number) {
  return apiRequest<{ deleted: boolean }>(`/candidate-profile/experiences/${id}`, {
    method: 'DELETE',
  });
}

export function addEducation(input: EducationInput) {
  return apiRequest<Education>('/candidate-profile/educations', {
    method: 'POST',
    body: input,
  });
}

export function updateEducation(id: number, input: EducationInput) {
  return apiRequest<Education>(`/candidate-profile/educations/${id}`, {
    method: 'PATCH',
    body: input,
  });
}

export function deleteEducation(id: number) {
  return apiRequest<{ deleted: boolean }>(`/candidate-profile/educations/${id}`, {
    method: 'DELETE',
  });
}

export function addLanguage(input: LanguageInput) {
  return apiRequest<Language>('/candidate-profile/languages', {
    method: 'POST',
    body: input,
  });
}

export function deleteLanguage(id: number) {
  return apiRequest<{ deleted: boolean }>(`/candidate-profile/languages/${id}`, {
    method: 'DELETE',
  });
}

export function syncSkills(names: string[]) {
  return apiRequest<CandidateProfile>('/candidate-profile/skills', {
    method: 'PUT',
    body: { names },
  });
}

export function syncSoftware(names: string[]) {
  return apiRequest<CandidateProfile>('/candidate-profile/software', {
    method: 'PUT',
    body: { names },
  });
}

export function uploadProfilePhoto(file: NativeFile) {
  const formData = new FormData();
  // Le cast est necessaire : les types de FormData sont ceux du web (Blob),
  // alors que React Native accepte un descripteur { uri, name, type }.
  formData.append('photo', file as unknown as Blob);

  return apiRequest<CandidateProfile>('/candidate-profile/photo', {
    method: 'POST',
    body: formData,
  });
}

export function removeProfilePhoto() {
  return apiRequest<CandidateProfile>('/candidate-profile/photo', { method: 'DELETE' });
}
