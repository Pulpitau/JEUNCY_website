export type CompensationPeriod = 'HOURLY' | 'MONTHLY' | 'YEARLY';

const PERIOD_SUFFIX: Record<CompensationPeriod, string> = {
  MONTHLY: '/ mois',
  YEARLY: '/ an',
  HOURLY: '/ heure',
};

// Rendu de la remuneration d'une offre — copie a l'identique de
// apps/web/src/lib/format-compensation.ts, pour que l'application et le site
// affichent exactement la meme phrase. Toute evolution doit etre reportee des
// deux cotes.
//
// « brut » est explicite : c'est ce qui est demande a l'entreprise, et un
// candidat qui lit un salaire sans cette mention suppose souvent du net.
// `legacyText` est l'ancien champ texte libre des offres anterieures a la
// saisie structuree : il continue de s'afficher tel quel, sinon ces offres —
// publiees et payees — n'afficheraient plus aucune remuneration.
export function formatCompensation(
  amount: number | null | undefined,
  period: CompensationPeriod | null | undefined,
  legacyText?: string | null,
): string | null {
  if (amount === null || amount === undefined || amount <= 0) {
    return legacyText?.trim() || null;
  }

  // fr-FR insere une espace insecable etroite entre les milliers et avant le
  // symbole : « 1 200 € ». Hermes (le moteur JS de React Native) embarque Intl
  // sur iOS comme sur Android.
  const formattedAmount = new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
    maximumFractionDigits: 0,
  }).format(amount);

  const suffix = period ? PERIOD_SUFFIX[period] : null;

  // Periode absente : le montant seul, plutot qu'inventer « / mois ».
  return suffix ? `${formattedAmount} brut ${suffix}` : `${formattedAmount} brut`;
}
