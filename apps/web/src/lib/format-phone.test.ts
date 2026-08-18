import { describe, expect, it } from 'vitest';
import { formatPhoneForDisplay, phoneToTelHref } from './format-phone';

// Ces tests existent a cause d'une panne de production (2026-08-18) : le
// numero avait ete espace directement dans le .env du serveur, ce que
// phpdotenv refuse — toute l'API est tombee. L'espacement se fait desormais
// ici, ou il ne peut plus rien casser. On verrouille le comportement.
describe('formatPhoneForDisplay', () => {
  it('espace un numero francais a 10 chiffres par paires', () => {
    expect(formatPhoneForDisplay('0987113349')).toBe('09 87 11 33 49');
    expect(formatPhoneForDisplay('0612345678')).toBe('06 12 34 56 78');
  });

  // Le .env n'est pas cense en contenir, mais si quelqu'un en remet un jour,
  // le resultat doit rester correct plutot que double-espace.
  it('normalise un numero deja espace ou ponctue', () => {
    expect(formatPhoneForDisplay('09 87 11 33 49')).toBe('09 87 11 33 49');
    expect(formatPhoneForDisplay('09.87.11.33.49')).toBe('09 87 11 33 49');
  });

  it('formate un numero international francais', () => {
    expect(formatPhoneForDisplay('+33987113349')).toBe('+33 9 87 11 33 49');
  });

  // Un numero non reconnu est rendu tel quel : mieux vaut un affichage brut
  // qu'un decoupage invente qui ferait composer un mauvais numero.
  it('rend tel quel ce qu il ne reconnait pas', () => {
    expect(formatPhoneForDisplay('+1 555 0100')).toBe('+1 555 0100');
    expect(formatPhoneForDisplay('123')).toBe('123');
    expect(formatPhoneForDisplay('')).toBe('');
  });
});

describe('phoneToTelHref', () => {
  it('ne garde que les chiffres dans le lien d appel', () => {
    expect(phoneToTelHref('09 87 11 33 49')).toBe('tel:0987113349');
    expect(phoneToTelHref('0987113349')).toBe('tel:0987113349');
    expect(phoneToTelHref('+33 9 87 11 33 49')).toBe('tel:+33987113349');
  });
});
