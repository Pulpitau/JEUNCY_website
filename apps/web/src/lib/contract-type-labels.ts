import { ContractType } from '@jeuncy/shared';

// Meme raison que OFFER_SECTOR_LABELS : Record exhaustif, donc une valeur
// ajoutee a ContractType sans libelle casse le build plutot que l'affichage.
export const CONTRACT_TYPE_LABELS: Record<ContractType, string> = {
  [ContractType.ALTERNANCE]: 'Alternance',
  [ContractType.SAISONNIER]: 'Saisonnier',
  [ContractType.BENEVOLAT]: 'Bénévolat',
  [ContractType.JOB_ETUDIANT]: 'Job étudiant',
  [ContractType.STAGE]: 'Stage',
};

export function contractTypeLabel(value: string): string {
  return CONTRACT_TYPE_LABELS[value as ContractType] ?? value;
}
