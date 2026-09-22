import type { AgeBand } from '@/lib/api/cvtheque';

// Le recruteur voit une tranche, jamais l'age exact (MOBILE.md §4.1). Les
// libelles disent « ans » explicitement : lus seuls, « <18 » ou « 26+ »
// pourraient passer pour n'importe quelle autre mesure.
export const AGE_BAND_LABELS: Record<AgeBand, string> = {
  '<18': 'Moins de 18 ans',
  '18-20': '18 à 20 ans',
  '21-25': '21 à 25 ans',
  '26+': '26 ans et plus',
};

export function ageBandLabel(band: AgeBand | null): string | null {
  return band ? (AGE_BAND_LABELS[band] ?? band) : null;
}
