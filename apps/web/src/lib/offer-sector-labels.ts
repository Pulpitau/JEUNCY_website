import { OfferSector } from '@jeuncy/shared';

// Record exhaustif a dessein : ajouter une valeur a OfferSector dans
// packages/shared sans lui donner de libelle ici ne compilera pas. Un secteur
// affiche en SCREAMING_SNAKE_CASE a un candidat ou a un recruteur est un bug
// qu'on prefere voir au build.
export const OFFER_SECTOR_LABELS: Record<OfferSector, string> = {
  [OfferSector.COMMERCE]: 'Commerce / Vente',
  [OfferSector.RESTAURATION_HOTELLERIE]: 'Restauration / Hôtellerie',
  [OfferSector.BTP]: 'Bâtiment / Travaux publics',
  [OfferSector.INDUSTRIE]: 'Industrie',
  [OfferSector.LOGISTIQUE_TRANSPORT]: 'Logistique / Transport',
  [OfferSector.SANTE_SOCIAL]: 'Santé / Social',
  [OfferSector.INFORMATIQUE_NUMERIQUE]: 'Informatique / Numérique',
  [OfferSector.ADMINISTRATIF_GESTION]: 'Administratif / Gestion',
  [OfferSector.BANQUE_ASSURANCE]: 'Banque / Assurance',
  [OfferSector.COMMUNICATION_MARKETING]: 'Communication / Marketing',
  [OfferSector.AGRICULTURE_ENVIRONNEMENT]: 'Agriculture / Environnement',
  [OfferSector.ART_CULTURE]: 'Art / Culture',
  [OfferSector.EDUCATION_FORMATION]: 'Éducation / Formation',
  [OfferSector.BEAUTE_BIEN_ETRE]: 'Beauté / Bien-être',
  [OfferSector.SPORT_ANIMATION]: 'Sport / Animation',
  [OfferSector.AUTRE]: 'Autre',
};

export function offerSectorLabel(value: string): string {
  return OFFER_SECTOR_LABELS[value as OfferSector] ?? value;
}

/** Paliers de rayon de recrutement, bornes du serveur : 5 a 100 km. */
export const RECRUITMENT_RADIUS_OPTIONS = [5, 10, 20, 30, 50, 100] as const;
