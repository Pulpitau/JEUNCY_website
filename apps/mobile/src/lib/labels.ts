import { ApplicationStatus, ContractType, WorkMode } from '@jeuncy/shared';

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
