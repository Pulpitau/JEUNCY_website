export type CompensationPeriod = 'HOURLY' | 'MONTHLY' | 'YEARLY';

export const COMPENSATION_PERIOD_OPTIONS: {
  value: CompensationPeriod;
  label: string;
  suffix: string;
}[] = [
  { value: 'MONTHLY', label: 'par mois', suffix: '/ mois' },
  { value: 'YEARLY', label: 'par an', suffix: '/ an' },
  { value: 'HOURLY', label: 'par heure', suffix: '/ heure' },
];

// Rendu de la remuneration d'une offre.
//
// Remplace un ancien champ texte libre affiche tel quel : une entreprise
// tapait « 1200 » et le candidat lisait « 1200 », sans devise ni periode —
// impossible de savoir s'il s'agissait d'un mensuel, d'un annuel ou d'un
// taux horaire. Le montant et la periode sont desormais saisis separement,
// et c'est ici qu'ils deviennent une phrase lisible.
//
// « brut » est explicite : c'est ce qui est demande a l'entreprise, et un
// candidat qui lit un salaire sans cette mention suppose souvent du net.
// `legacyText` est l'ancien champ texte libre. La migration ne convertit que
// ce qu'elle lit sans ambiguite ; tout le reste (« SMIC + primes », « Selon
// profil », « 800 € / mois + tickets restaurant ») garde son texte et
// continue de s'afficher ainsi. Sans ce repli, ces offres — publiees et
// payees — n'afficheraient plus aucune remuneration, et leur proprietaire ne
// pourrait meme pas la ressaisir : une offre publiee n'est plus modifiable.
export function formatCompensation(
  amount: number | null | undefined,
  period: CompensationPeriod | null | undefined,
  legacyText?: string | null,
): string | null {
  if (amount === null || amount === undefined || amount <= 0) {
    return legacyText?.trim() || null;
  }

  // fr-FR insere une espace insecable etroite entre les milliers et avant le
  // symbole : « 1 200 € », jamais coupe en fin de ligne.
  const formattedAmount = new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
    maximumFractionDigits: 0,
  }).format(amount);

  const suffix = COMPENSATION_PERIOD_OPTIONS.find(
    (option) => option.value === period,
  )?.suffix;

  // Periode absente (donnee ancienne ou incomplete) : on affiche le montant
  // seul plutot que d'inventer « / mois », qui serait une information fausse.
  return suffix ? `${formattedAmount} brut ${suffix}` : `${formattedAmount} brut`;
}
