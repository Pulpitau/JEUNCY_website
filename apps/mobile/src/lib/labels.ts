import {
  ApplicationStatus,
  ContractType,
  DrivingLicenseCategory,
  OfferSector,
  VerificationStatus,
  WorkMode,
} from '@jeuncy/shared';

import type { AgeBand } from './api/discover';

// Libelles francais des enums partagees. Le web les redefinit dans chaque
// composant qui en a besoin ; ici ils sont centralises, une seule fois.
// Toute nouvelle valeur d'enum dans packages/shared doit etre ajoutee ici,
// sinon TypeScript refuse la compilation (Record exhaustif) — c'est voulu.

export const CONTRACT_TYPE_LABELS: Record<ContractType, string> = {
  [ContractType.ALTERNANCE]: 'Alternance',
  [ContractType.SAISONNIER]: 'Saisonnier',
  [ContractType.BENEVOLAT]: 'Bénévolat',
  [ContractType.JOB_ETUDIANT]: 'Job étudiant',
  [ContractType.STAGE]: 'Stage',
};

export const WORK_MODE_LABELS: Record<WorkMode, string> = {
  [WorkMode.PRESENTIEL]: 'Présentiel',
  [WorkMode.HYBRIDE]: 'Hybride',
  [WorkMode.DISTANCIEL]: 'Distanciel',
};

export const APPLICATION_STATUS_LABELS: Record<ApplicationStatus, string> = {
  [ApplicationStatus.SENT]: 'Envoyée',
  [ApplicationStatus.SEEN]: 'Vue',
  [ApplicationStatus.INTERVIEW]: 'Entretien',
  [ApplicationStatus.ACCEPTED]: 'Acceptée',
  [ApplicationStatus.REJECTED]: 'Refusée',
};

export const OFFER_SECTOR_LABELS: Record<OfferSector, string> = {
  [OfferSector.COMMERCE]: 'Commerce et vente',
  [OfferSector.RESTAURATION_HOTELLERIE]: 'Restauration et hôtellerie',
  [OfferSector.BTP]: 'Bâtiment et travaux publics',
  [OfferSector.INDUSTRIE]: 'Industrie',
  [OfferSector.LOGISTIQUE_TRANSPORT]: 'Logistique et transport',
  [OfferSector.SANTE_SOCIAL]: 'Santé et social',
  [OfferSector.INFORMATIQUE_NUMERIQUE]: 'Informatique et numérique',
  [OfferSector.ADMINISTRATIF_GESTION]: 'Administratif et gestion',
  [OfferSector.BANQUE_ASSURANCE]: 'Banque et assurance',
  [OfferSector.COMMUNICATION_MARKETING]: 'Communication et marketing',
  [OfferSector.AGRICULTURE_ENVIRONNEMENT]: 'Agriculture et environnement',
  [OfferSector.ART_CULTURE]: 'Art et culture',
  [OfferSector.EDUCATION_FORMATION]: 'Éducation et formation',
  [OfferSector.BEAUTE_BIEN_ETRE]: 'Beauté et bien-être',
  [OfferSector.SPORT_ANIMATION]: 'Sport et animation',
  [OfferSector.AUTRE]: 'Autre',
};

// Categories du permis. Le libelle dit a quoi elles servent : « B » ne parle
// qu'a ceux qui l'ont deja passe, « Voiture » parle a tout le monde.
export const DRIVING_LICENSE_LABELS: Record<DrivingLicenseCategory, string> = {
  [DrivingLicenseCategory.AM]: 'AM — scooter 50 cm³',
  [DrivingLicenseCategory.A1]: 'A1 — moto 125 cm³',
  [DrivingLicenseCategory.A2]: 'A2 — moto intermédiaire',
  [DrivingLicenseCategory.A]: 'A — moto',
  [DrivingLicenseCategory.B1]: 'B1 — quadricycle lourd',
  [DrivingLicenseCategory.B]: 'B — voiture',
  [DrivingLicenseCategory.BE]: 'BE — voiture avec remorque',
  [DrivingLicenseCategory.C]: 'C — poids lourd',
  [DrivingLicenseCategory.D]: 'D — transport en commun',
};

export const VERIFICATION_STATUS_LABELS: Record<VerificationStatus, string> = {
  [VerificationStatus.PENDING]: 'Vérification en cours',
  [VerificationStatus.VERIFIED]: 'Entreprise vérifiée',
  [VerificationStatus.REJECTED]: 'Vérification refusée',
};

/**
 * Tranches d'age. « Moins de 18 ans » et non « 16-17 » : l'application impose
 * 16 ans, mais le site en accepte 15 depuis toujours et les profils existants
 * ne disparaissent pas — une borne basse affichee serait fausse pour eux
 * (decision du 2026-09-22).
 */
export const AGE_BAND_LABELS: Record<AgeBand, string> = {
  '<18': 'Moins de 18 ans',
  '18-20': '18 à 20 ans',
  '21-25': '21 à 25 ans',
  '26+': '26 ans et plus',
};
