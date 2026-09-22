import type { ContractType, OfferSector } from '@jeuncy/shared';
import { apiDownload, apiRequest } from './client';

// Tranche d'age montree a un employeur avant le dossier. Jamais l'age exact :
// une tranche suffit a estimer le cout d'un alternant, elle ne permet pas de
// retrouver quelqu'un. Null quand le profil n'a pas de date de naissance.
export type AgeBand = '<18' | '18-20' | '21-25' | '26+';

// LA carte candidat, unique forme d'exposition d'un profil avant candidature
// (MOBILE.md §4.3, App\Presenters\CandidateCardPresenter cote serveur). Le
// deck de l'app et la CVtheque du site montrent exactement ceci.
//
// Ce type est une LISTE BLANCHE : ce qui n'y figure pas n'est pas envoye par
// le serveur, et ne doit pas y etre ajoute sans revoir le presenteur. Ni nom
// complet, ni ville, ni email, ni telephone, ni date de naissance, ni URL de
// CV — ces champs n'existent plus dans la reponse, pas seulement dans
// l'affichage.
export interface CandidateCard {
  id: number;
  first_name: string;
  // Premiere lettre du nom, en majuscule : « Léa G. ».
  last_name_initial: string;
  age_band: AgeBand | null;
  headline: string | null;
  pitch: string | null;
  wanted_contract_types: ContractType[];
  wanted_sectors: OfferSector[];
  // Present uniquement quand une offre est en contexte (deck employeur) :
  // « sa zone de mobilite couvre ton offre », jamais une distance ni une
  // commune. Absent de la CVtheque, qui n'a pas d'offre de reference.
  mobility?: { covers_offer: boolean };
  has_driving_license: boolean;
  driving_license_categories: string[];
  has_vehicle: boolean;
  available_from: string | null;
  skills: { id: number; name: string; in_common: boolean }[];
  software: { id: number; name: string }[];
  languages: { name: string; level: string | null }[];
  educations: {
    degree: string;
    school: string | null;
    field_of_study: string | null;
    start_date: string | null;
    end_date: string | null;
  }[];
  // Ni lieu ni description : deux champs de texte libre ou une adresse ou un
  // numero de telephone finissent regulierement.
  experiences: {
    title: string;
    company: string | null;
    start_date: string | null;
    end_date: string | null;
  }[];
  // Null tant que le candidat n'a pas coche « montrer ma photo aux
  // entreprises » (opt-in, defaut false).
  photo_url: string | null;
  has_uploaded_cv: boolean;
}

// La fiche n'ajoute plus de coordonnees : elle ajoute seulement le droit de
// telecharger le CV, qui s'ouvre quand le candidat postule a une offre de
// cette entreprise.
export interface CvthequeCandidateDetail extends CandidateCard {
  cv_available: boolean;
}

export interface CvthequeSearchFilters {
  q?: string;
  language?: string;
  // Colonne structuree, remplace l'ancien filtre sur le texte libre.
  has_driving_license?: boolean;
  age_min?: number;
  age_max?: number;
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
  if (filters.language) params.set('language', filters.language);
  if (filters.has_driving_license) params.set('has_driving_license', '1');
  if (filters.age_min) params.set('age_min', String(filters.age_min));
  if (filters.age_max) params.set('age_max', String(filters.age_max));
  if (filters.page && filters.page > 1) params.set('page', String(filters.page));
  // Tableaux serialises en skills[]= : c'est la forme que Laravel parse en
  // tableau cote Form Request.
  filters.skills?.forEach((s) => params.append('skills[]', s));
  filters.software?.forEach((s) => params.append('software[]', s));
  const qs = params.toString();
  return qs ? `?${qs}` : '';
}

export function searchCvtheque(filters: CvthequeSearchFilters) {
  return apiRequest<Paginated<CandidateCard>>(`/cvtheque${toQueryString(filters)}`);
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
// Repond 403 CV_NOT_SHARED tant que le candidat n'a pas postule.
export function downloadCvthequeCv(id: number) {
  return apiDownload(`/cvtheque/${id}/cv`);
}
