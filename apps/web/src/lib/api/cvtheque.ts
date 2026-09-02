import { apiDownload, apiRequest } from './client';

// Profil tel qu'il apparait DANS LA LISTE. Volontairement pauvre en donnees
// personnelles : le backend ne renvoie ici ni telephone, ni adresse, ni date
// de naissance, ni email (voir CvthequeService::LIST_COLUMNS). Ne pas etendre
// ce type sans revoir la minimisation cote serveur — ajouter un champ ici ne
// suffirait pas a le faire apparaitre, et ne devrait pas.
export interface CvthequeCandidate {
  id: number;
  first_name: string;
  last_name: string;
  headline: string | null;
  city: string | null;
  photo_url: string | null;
  bio: string | null;
  driving_license: string | null;
  skills: { id: number; name: string }[];
  software: { id: number; name: string }[];
  languages: { id: number; name: string; level: string | null }[];
}

// Fiche complete : les coordonnees apparaissent ici, une fois le profil ouvert.
export interface CvthequeCandidateDetail extends CvthequeCandidate {
  phone: string | null;
  address: string | null;
  postal_code: string | null;
  birth_date: string | null;
  hobbies: string | null;
  video_url: string | null;
  portfolio_url: string | null;
  linkedin_url: string | null;
  user: { id: number; email: string };
  experiences: {
    id: number;
    title: string;
    company: string | null;
    location: string | null;
    start_date: string | null;
    end_date: string | null;
    description: string | null;
  }[];
  educations: {
    id: number;
    degree: string;
    school: string | null;
    field_of_study: string | null;
    start_date: string | null;
    end_date: string | null;
  }[];
  // Indique si le CV telechargeable est celui que le candidat a lui-meme
  // depose (son PDF Canva, Word...) plutot qu'une mise en page produite par
  // Jeuncy. L'URL du fichier, elle, n'est jamais transmise : le
  // telechargement passe obligatoirement par downloadCvthequeCv.
  has_uploaded_cv: boolean;
}

export interface CvthequeSearchFilters {
  q?: string;
  city?: string;
  language?: string;
  driving_license?: boolean;
  skills?: string[];
  software?: string[];
  page?: number;
}

export interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
}

function toQueryString(filters: CvthequeSearchFilters): string {
  const params = new URLSearchParams();
  if (filters.q) params.set('q', filters.q);
  if (filters.city) params.set('city', filters.city);
  if (filters.language) params.set('language', filters.language);
  if (filters.driving_license) params.set('driving_license', '1');
  if (filters.page && filters.page > 1) params.set('page', String(filters.page));
  // Tableaux serialises en skills[]= : c'est la forme que Laravel parse en
  // tableau cote Form Request.
  filters.skills?.forEach((s) => params.append('skills[]', s));
  filters.software?.forEach((s) => params.append('software[]', s));
  const qs = params.toString();
  return qs ? `?${qs}` : '';
}

export function searchCvtheque(filters: CvthequeSearchFilters) {
  return apiRequest<Paginated<CvthequeCandidate>>(`/cvtheque${toQueryString(filters)}`);
}

export function getCvthequeCandidate(id: number) {
  return apiRequest<CvthequeCandidateDetail>(`/cvtheque/${id}`);
}

export function getCvthequeAccess() {
  return apiRequest<{ has_access: boolean }>('/cvtheque/access');
}

// Telecharge le CV du candidat. Le serveur renvoie le PDF lui-meme (jamais son
// URL) et journalise l'acces : chaque appel laisse une trace nominative,
// exigence RGPD assumee cote produit — un CV telecharge quitte la plateforme.
export function downloadCvthequeCv(id: number) {
  return apiDownload(`/cvtheque/${id}/cv`);
}
