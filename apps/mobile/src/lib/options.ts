// Listes de choix partagees par les formulaires d'organisation et d'offre.
// Copiees du web (lib/diploma-level-options.ts et JobOfferForm.tsx) : les
// valeurs sont stockees telles quelles en base, l'app et le site doivent
// proposer exactement les memes.

export const DIPLOMA_LEVEL_OPTIONS = [
  'CAP / BEP',
  'Bac / Bac Pro',
  'Bac+1',
  'Bac+2 (BTS, DUT/BUT)',
  'Bac+3 (Licence, Bachelor)',
  'Bac+4',
  'Bac+5 (Master, Ingénieur)',
  'Bac+6 et plus (Doctorat, MBA)',
] as const;

export const DIPLOMA_LEVEL_SELECT = DIPLOMA_LEVEL_OPTIONS.map((value) => ({
  value,
  label: value,
}));

// Formulaire d'offre (JobOfferForm.tsx cote web). Le niveau vise d'une offre
// de CFA utilise une liste plus courte que celle de la fiche du CFA — c'est
// ainsi sur le site, on ne l'harmonise pas ici pour rester identique.
export const EXPERIENCE_LEVEL_OPTIONS = [
  'Débutant accepté',
  '1 à 2 ans',
  '3 à 5 ans',
  '5 ans et plus',
] as const;

export const OFFER_DIPLOMA_LEVEL_OPTIONS = [
  'CAP / BEP',
  'Bac',
  'Bac+2 (BTS, DUT)',
  'Bac+3 (Licence, Bachelor)',
  'Bac+5 (Master, Ingénieur)',
] as const;

export const EXPERIENCE_LEVEL_SELECT = EXPERIENCE_LEVEL_OPTIONS.map((value) => ({
  value,
  label: value,
}));
export const OFFER_DIPLOMA_LEVEL_SELECT = OFFER_DIPLOMA_LEVEL_OPTIONS.map((value) => ({
  value,
  label: value,
}));
