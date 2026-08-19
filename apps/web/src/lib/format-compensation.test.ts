import { describe, expect, it } from 'vitest';
import { formatCompensation } from './format-compensation';

// Les espaces produits par Intl.NumberFormat en fr-FR sont insecables (U+202F
// pour les milliers, U+00A0 avant le symbole), pas des espaces ordinaires :
// les comparer a " " ferait echouer des tests pourtant corrects.
// Ecrits en echappement et non en caracteres litteraux : invisibles dans
// l'editeur, ils seraient indistinguables d'une espace ordinaire a la
// relecture.
const normalize = (value: string | null) =>
  value?.replace(/[\u202f\u00a0]/g, ' ') ?? null;

describe('formatCompensation', () => {
  it('affiche le montant en euros avec la periode', () => {
    expect(normalize(formatCompensation(1200, 'MONTHLY'))).toBe('1 200 € brut / mois');
    expect(normalize(formatCompensation(24000, 'YEARLY'))).toBe('24 000 € brut / an');
    expect(normalize(formatCompensation(12, 'HOURLY'))).toBe('12 € brut / heure');
  });

  // Cas des donnees reprises de l'ancien champ texte, ou la periode n'a pas pu
  // etre deduite : mieux vaut un montant seul qu'un « / mois » invente.
  it('omet la periode quand elle est absente plutot que d en supposer une', () => {
    expect(normalize(formatCompensation(1200, null))).toBe('1 200 € brut');
  });

  // Une offre benevole n'a pas de remuneration : l'appelant ne doit rien
  // afficher, d'ou null et pas une chaine vide qui laisserait un separateur
  // orphelin dans « Ville · Contrat · ... ».
  it('renvoie null quand il n y a pas de montant', () => {
    expect(formatCompensation(null, 'MONTHLY')).toBeNull();
    expect(formatCompensation(undefined, 'MONTHLY')).toBeNull();
    expect(formatCompensation(0, 'MONTHLY')).toBeNull();
  });

  it('n affiche pas de centimes', () => {
    expect(normalize(formatCompensation(1200, 'MONTHLY'))).not.toContain(',');
  });
});
