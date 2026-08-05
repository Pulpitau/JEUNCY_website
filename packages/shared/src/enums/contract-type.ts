export const ContractType = {
  ALTERNANCE: 'ALTERNANCE',
  SAISONNIER: 'SAISONNIER',
  BENEVOLAT: 'BENEVOLAT',
  JOB_ETUDIANT: 'JOB_ETUDIANT',
  STAGE: 'STAGE',
} as const;

export type ContractType = (typeof ContractType)[keyof typeof ContractType];
