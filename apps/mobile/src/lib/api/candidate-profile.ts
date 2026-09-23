import type { ContractType, DrivingLicenseCategory, OfferSector } from '@jeuncy/shared';
import { File } from 'expo-file-system';

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
  // « Ce que je cherche » et « Mobilite » (lot 1). Toutes ces colonnes
  // acceptent l'absence de reponse : un profil muet reste eligible a tout,
  // ce qui est le cas des 115 profils anterieurs a cet ecran.
  wanted_contract_types: ContractType[] | null;
  wanted_sectors: OfferSector[] | null;
  /** Rayon de recherche du candidat, en km (5 a 100). */
  search_radius_km: number;
  /** Rayon jusqu'ou il accepte d'aller travailler : c'est CELUI que l'employeur voit. */
  mobility_radius_km: number;
  has_driving_license: boolean;
  driving_license_categories: DrivingLicenseCategory[] | null;
  has_vehicle: boolean;
  available_from: string | null;
  /** 160 caracteres, sans coordonnees (le serveur les refuse). */
  pitch: string | null;
  /**
   * Defaut : faux. Un portrait est la donnee la plus identifiante d'un
   * mineur ; il ne part chez un employeur que sur un oui explicite.
   */
  show_photo_to_employers: boolean;
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

// Fichier local a envoyer en multipart : ce que renvoient expo-image-picker
// et expo-document-picker.
export interface NativeFile {
  uri: string;
  name: string;
  type: string;
}

// Convertit un fichier local en une part acceptee par le fetch d'Expo.
//
// POURQUOI. Expo SDK 54+ remplace le fetch de React Native par le sien
// (expo/src/winter/runtime.native.ts), qui assemble lui-meme le multipart
// et n'accepte qu'une chaine, un Blob, ou un objet dote de bytes() — le File
// d'expo-file-system. Le format historique de React Native { uri, name,
// type } n'entre dans aucune de ces cases et echoue avec « Unsupported
// FormDataPart implementation » : c'est ce qui a bloque la photo de profil
// le 2026-09-11, sans reponse du serveur puisque rien n'etait envoye.
//
// Le cast est necessaire : File implemente l'interface Blob sans etendre la
// classe, et les types de FormData sont ceux du DOM.
export function toFormDataPart(file: NativeFile): Blob {
  return new File(file.uri) as unknown as Blob;
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
  formData.append('photo', toFormDataPart(file));

  return apiRequest<CandidateProfile>('/candidate-profile/photo', {
    method: 'POST',
    body: formData,
  });
}

export function removeProfilePhoto() {
  return apiRequest<CandidateProfile>('/candidate-profile/photo', { method: 'DELETE' });
}

// --- CV -------------------------------------------------------------------

/** Genere un CV PDF a partir du profil (rendu cote serveur, dompdf). */
export function generateCv() {
  return apiRequest<GeneratedCv>('/candidate-profile/cv', { method: 'POST' });
}

export function listGeneratedCvs() {
  return apiRequest<GeneratedCv[]>('/candidate-profile/cv');
}

// CV depose par le candidat lui-meme, propose aux recruteurs en priorite sur
// un CV genere : c'est le document qu'il a choisi (CvthequeService).
export function uploadOwnCv(file: NativeFile) {
  const formData = new FormData();
  formData.append('cv_file', toFormDataPart(file));

  return apiRequest<CandidateProfile>('/candidate-profile/cv-file', {
    method: 'POST',
    body: formData,
  });
}

export function removeOwnCv() {
  return apiRequest<CandidateProfile>('/candidate-profile/cv-file', { method: 'DELETE' });
}

// Suggestions lues dans un PDF. Rien n'est devine : seuls des formats non
// ambigus (email, telephone, code postal, LinkedIn, permis) et des noms deja
// connus de Jeuncy (competences, logiciels) sont proposes — voir
// CvImportService cote serveur. Rien n'est enregistre par cet appel : le
// candidat relit, puis l'application applique ce qu'il a garde.
export interface ImportedCvData {
  first_name: string | null;
  last_name: string | null;
  email: string | null;
  phone: string | null;
  postal_code: string | null;
  linkedin_url: string | null;
  driving_license: string | null;
  skills: string[];
  software: string[];
  languages: { name: string; level: string | null }[];
  experiences: {
    title: string;
    company: string | null;
    start_date: string | null;
    end_date: string | null;
    description: string | null;
  }[];
  educations: {
    degree: string;
    school: string | null;
    start_date: string | null;
    end_date: string | null;
  }[];
}

export function importCv(file: NativeFile) {
  const formData = new FormData();
  formData.append('cv', toFormDataPart(file));

  return apiRequest<ImportedCvData>('/candidate-profile/cv/import', {
    method: 'POST',
    body: formData,
  });
}

// ---------------------------------------------------------------------------
// « Ce que je cherche » et « Où » (lot 1, MOBILE.md §3.1 et §6)
// ---------------------------------------------------------------------------

/**
 * Tout est facultatif : l'ecran se remplit par morceaux, et un PUT partiel
 * ne doit pas effacer ce qu'il ne mentionne pas (le serveur valide en
 * `sometimes`). Un champ absent veut dire « ne touche pas », un champ a null
 * veut dire « efface ».
 */
export interface CandidatePreferencesInput {
  wanted_contract_types?: ContractType[];
  /** Trois au maximum : au-dela, « ce que je cherche » ne cherche plus rien. */
  wanted_sectors?: OfferSector[];
  search_radius_km?: number;
  mobility_radius_km?: number;
  has_driving_license?: boolean;
  driving_license_categories?: DrivingLicenseCategory[];
  has_vehicle?: boolean;
  available_from?: string | null;
  pitch?: string | null;
  show_photo_to_employers?: boolean;
}

export function updatePreferences(input: CandidatePreferencesInput) {
  return apiRequest<CandidateProfile>('/candidate-profile/preferences', {
    method: 'PUT',
    body: input,
  });
}

/** D'ou vient la position utilisee par la pile du candidat. */
export interface CandidateLocation {
  location_source: 'PROFILE' | 'DEVICE' | null;
  device_located_at?: string | null;
}

/**
 * Position GPS du telephone, pour la pile du CANDIDAT seulement.
 *
 * Le serveur arrondit a deux decimales avant de stocker (~1 km), et ne
 * renvoie jamais les coordonnees. Le deck employeur ne lit structurellement
 * jamais ces colonnes : la decision « aucune distance cote employeur » est
 * garantie par le schema, pas par une regle qu'on peut oublier.
 */
export function updateLocation(latitude: number, longitude: number) {
  return apiRequest<CandidateLocation>('/candidate-profile/location', {
    method: 'PUT',
    body: { latitude, longitude },
  });
}

/** Revient a la commune declaree du profil. */
export function clearLocation() {
  return apiRequest<CandidateLocation>('/candidate-profile/location', {
    method: 'DELETE',
  });
}
