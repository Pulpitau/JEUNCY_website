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

export interface CandidateProfile {
  id: number;
  user_id: number;
  first_name: string;
  last_name: string;
  phone: string | null;
  birth_date: string | null;
  address: string | null;
  city: string | null;
  postal_code: string | null;
  bio: string | null;
  photo_url: string | null;
  experiences: Experience[];
  educations: Education[];
  skills: Skill[];
}

export interface GeneratedCv {
  id: number;
  candidate_profile_id: number;
  file_url: string;
  generated_at: string;
}

export interface CandidateProfileInput {
  first_name: string;
  last_name: string;
  phone?: string | null;
  birth_date?: string | null;
  address?: string | null;
  city?: string | null;
  postal_code?: string | null;
  bio?: string | null;
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

export function syncSkills(names: string[]) {
  return apiRequest<CandidateProfile>('/candidate-profile/skills', {
    method: 'PUT',
    body: { names },
  });
}

export function generateCv() {
  return apiRequest<GeneratedCv>('/candidate-profile/cv', { method: 'POST' });
}

export function listGeneratedCvs() {
  return apiRequest<GeneratedCv[]>('/candidate-profile/cv');
}
