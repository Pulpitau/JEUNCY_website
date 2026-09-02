import { apiRequest } from './client';

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
  // Droit d'opposition a la CVtheque (RGPD art. 21) : true par defaut cote
  // base, le candidat peut se retirer depuis son profil.
  is_visible_in_cvtheque: boolean;
  // CV que le candidat a lui-meme depose. Propose aux recruteurs en priorite
  // sur un CV genere par Jeuncy : c'est le document qu'il a choisi.
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

// Suggestions issues de la lecture d'un PDF. Rien n'est devine : seuls des
// formats non ambigus (email, telephone, code postal, LinkedIn, permis) et des
// noms deja connus de Jeuncy (competences, logiciels) sont proposes — voir
// CvImportService cote serveur. Le candidat relit et applique.
export interface ImportedCvData {
  email: string | null;
  phone: string | null;
  postal_code: string | null;
  linkedin_url: string | null;
  driving_license: string | null;
  skills: string[];
  software: string[];
  languages: { name: string; level: string | null }[];
  raw_text: string;
}

export interface CandidateProfileInput {
  first_name: string;
  last_name: string;
  headline?: string | null;
  phone?: string | null;
  birth_date?: string | null;
  address?: string | null;
  city?: string | null;
  postal_code?: string | null;
  bio?: string | null;
  hobbies?: string | null;
  driving_license?: string | null;
  video_url?: string | null;
  portfolio_url?: string | null;
  linkedin_url?: string | null;
  is_visible_in_cvtheque?: boolean;
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

export function getMyProfile() {
  return apiRequest<CandidateProfile>('/candidate-profile');
}

export function createProfile(input: CandidateProfileInput) {
  return apiRequest<CandidateProfile>('/candidate-profile', {
    method: 'POST',
    body: input,
  });
}

export function updateProfile(input: Partial<CandidateProfileInput>) {
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

export function uploadProfilePhoto(file: File) {
  const formData = new FormData();
  formData.append('photo', file);

  return apiRequest<CandidateProfile>('/candidate-profile/photo', {
    method: 'POST',
    body: formData,
  });
}

export function removeProfilePhoto() {
  return apiRequest<CandidateProfile>('/candidate-profile/photo', { method: 'DELETE' });
}

export function generateCv() {
  return apiRequest<GeneratedCv>('/candidate-profile/cv', { method: 'POST' });
}

export function listGeneratedCvs() {
  return apiRequest<GeneratedCv[]>('/candidate-profile/cv');
}

export function importCv(file: File) {
  const formData = new FormData();
  formData.append('cv', file);

  return apiRequest<ImportedCvData>('/candidate-profile/cv/import', {
    method: 'POST',
    body: formData,
  });
}

// Depot du CV du candidat, conserve tel quel. A ne pas confondre avec importCv
// ci-dessus, qui lit un PDF pour proposer des donnees sans conserver le fichier.
export function uploadOwnCv(file: File) {
  const formData = new FormData();
  formData.append('cv_file', file);

  return apiRequest<CandidateProfile>('/candidate-profile/cv-file', {
    method: 'POST',
    body: formData,
  });
}

export function removeOwnCv() {
  return apiRequest<CandidateProfile>('/candidate-profile/cv-file', { method: 'DELETE' });
}
