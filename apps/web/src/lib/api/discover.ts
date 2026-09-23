import { apiRequest } from './client';
import type { PublicJobOffer } from './job-offers';

// `GET discover/offers` — la pile du candidat, côté serveur.
//
// Le site n'a pas de pile de cartes (le geste au doigt est l'affaire de
// l'application) : il se sert de cette route pour UNE chose, savoir quels
// recruteurs se sont déjà déclarés intéressés. C'est `employer_interested`,
// et c'est la seule façon de l'apprendre — rien d'autre dans l'API ne
// l'expose, à dessein : un intérêt employeur est une invitation, pas une
// liste consultable.
//
// Le serveur ne renvoie que des offres éligibles, déjà triées, et exclut ce
// que le candidat a déjà jugé ou postulé. Il n'y a donc rien à filtrer ici.

export interface DeckJobOffer extends PublicJobOffer {
  /** Distance en km, arrondie au dixième. Null si le profil n'est pas géocodé. */
  distance_km: number | null;
  /** Ce recruteur a déjà dit oui : un oui du candidat fait match immédiatement. */
  employer_interested: boolean;
  already_applied: boolean;
}

export interface DeckMeta {
  radius_km: number;
  department: string | null;
  has_coordinates: boolean;
  location_source: 'PROFILE' | 'DEVICE' | null;
  /** Portée réellement utilisée : « radius », « department » ou « france ». */
  scope: string;
  partner_scope: string;
  quota: { limit: number; used: number; active: boolean };
}

export interface DiscoverOffersResponse {
  jeuncy: DeckJobOffer[];
  partner: {
    data: unknown[];
    current_page: number;
    last_page: number;
    total: number;
  };
  meta: DeckMeta;
}

export function discoverOffers(page = 1) {
  return apiRequest<DiscoverOffersResponse>(`/discover/offers?page=${page}`);
}
