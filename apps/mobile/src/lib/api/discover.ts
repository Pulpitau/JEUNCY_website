import type { ContractType, DrivingLicenseCategory, OfferSector } from '@jeuncy/shared';

import { apiRequest } from './client';
import type { ExternalJobOffer } from './external-offers';
import type { Paginated, PublicJobOffer, Skill } from './job-offers';

// La pile « Decouvrir » cote employeur : GET discover/candidates.
//
// Le serveur ne renvoie QUE des candidats eligibles (profil visible, age
// minimum, contrat compatible, les deux rayons de mobilite qui se couvrent,
// non bloque, departement ouvert) et jamais de score. Il n'y a donc rien a
// filtrer ni a trier ici : la pile arrive dans l'ordre ou elle se joue.
//
// La forme de la carte est celle d'App\Presenters\CandidateCardPresenter, la
// regle d'exposition unique (MOBILE.md §4.3). Ce qui n'y est pas ne doit
// jamais etre demande ailleurs pour completer l'ecran : pas de nom complet,
// pas de ville, pas d'email ni de telephone, pas de CV. Un champ manquant
// sur cette carte est une decision, pas un oubli.

export interface CardSkill extends Skill {
  /** Attendue par l'offre visee : ces competences sont affichees en premier. */
  in_common: boolean;
}

export interface CardLanguage {
  name: string;
  level: string | null;
}

export interface CardEducation {
  degree: string | null;
  school: string | null;
  field_of_study: string | null;
  start_date: string | null;
  end_date: string | null;
}

export interface CardExperience {
  title: string | null;
  company: string | null;
  start_date: string | null;
  end_date: string | null;
}

/** Tranches d'age de `CandidateProfile::getAgeBandAttribute`, jamais l'age exact. */
export type AgeBand = '<18' | '18-20' | '21-25' | '26+';

export interface CandidateCard {
  id: number;
  first_name: string;
  /** Initiale du nom de famille, en majuscule. Le nom complet arrive avec le dossier. */
  last_name_initial: string | null;
  age_band: AgeBand | null;
  headline: string | null;
  pitch: string | null;
  wanted_contract_types: ContractType[];
  wanted_sectors: OfferSector[];
  has_driving_license: boolean;
  driving_license_categories: DrivingLicenseCategory[];
  has_vehicle: boolean;
  available_from: string | null;
  skills: CardSkill[];
  software: Skill[];
  languages: CardLanguage[];
  educations: CardEducation[];
  experiences: CardExperience[];
  /** Renseigne seulement si le candidat a autorise son portrait (defaut : non). */
  photo_url: string | null;
  has_uploaded_cv: boolean;
  /**
   * « Sa zone de mobilite couvre ton offre » — jamais une distance, jamais une
   * ville de residence (decision du 2026-09-22 : L1132-1). Absent du detail
   * d'un match, present dans la pile.
   */
  mobility?: { covers_offer: boolean };
}

export interface DeckCandidateCard extends CandidateCard {
  /** Le candidat a deja dit « Ca m'interesse » : un oui de l'employeur fait match. */
  candidate_interested: boolean;
  skills_in_common: string[];
}

export function discoverCandidates(jobOfferId: number, page = 1) {
  const params = new URLSearchParams({
    job_offer_id: String(jobOfferId),
    page: String(page),
  });

  return apiRequest<Paginated<DeckCandidateCard>>(`/discover/candidates?${params}`);
}

// ---------------------------------------------------------------------------
// La pile « Decouvrir » cote candidat : GET discover/offers
// ---------------------------------------------------------------------------

/**
 * Offre Jeuncy de la pile.
 *
 * `distance_km` n'existe QUE de ce cote : le candidat a le droit de savoir ou
 * est le poste, l'employeur n'a pas le droit de savoir ou habite le candidat
 * (decision du 2026-09-22, L1132-1).
 */
export interface DeckJeuncyOffer extends PublicJobOffer {
  distance_km: number | null;
  /** L'employeur a dit oui en premier : un oui du candidat fait match tout de suite. */
  employer_interested: boolean;
  /** Toujours faux dans la pile, qui exclut les offres deja postulees. */
  already_applied: boolean;
  /**
   * Delai median de reponse de cet employeur, en jours. Null tant qu'il n'a
   * pas traite cinq candidatures : un chiffre tire d'un seul cas serait faux
   * dans les deux sens, et c'est precisement celui qu'un candidat croirait.
   */
  employer_response_days: number | null;
}

export interface DeckPartnerOffer extends ExternalJobOffer {
  distance_km: number | null;
}

/**
 * Portee reellement utilisee par le serveur, apres cascade.
 *
 * Les deux piles cascadent SEPAREMENT (rayon, sinon departement, sinon toute
 * la France) : en production il n'y a qu'une offre Jeuncy publiee pour 7 779
 * partenaires, et les lier enverrait vingt offres de toute la France des que
 * l'unique offre Jeuncy bascule.
 */
export type DeckScope = 'radius' | 'department' | 'france';

export interface DeckMeta {
  radius_km: number;
  department: string | null;
  has_coordinates: boolean;
  /** D'ou vient la position : commune declaree, ou GPS du telephone. */
  location_source: 'PROFILE' | 'DEVICE' | null;
  scope: DeckScope;
  partner_scope: DeckScope;
  /**
   * Le quota ne s'active que si la pile compte au moins autant d'offres que
   * lui : plafonner a 20 quelqu'un qui n'en a que 3 a portee le bloquerait
   * sans rien proteger.
   */
  quota: { limit: number; used: number; active: boolean };
}

export interface DiscoverOffersResponse {
  /** Selection du jour : au plus 20, pas de curseur infini de ce cote. */
  jeuncy: DeckJeuncyOffer[];
  partner: Paginated<DeckPartnerOffer>;
  meta: DeckMeta;
}

export function discoverOffers(page = 1) {
  return apiRequest<DiscoverOffersResponse>(`/discover/offers?page=${page}`);
}
