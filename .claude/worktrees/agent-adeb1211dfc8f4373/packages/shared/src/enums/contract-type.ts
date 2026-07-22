export const ContractType = {
  ALTERNANCE: 'ALTERNANCE',
  SAISONNIER: 'SAISONNIER',
  BENEVOLAT: 'BENEVOLAT',
} as const;

export type ContractType = (typeof ContractType)[keyof typeof ContractType];
