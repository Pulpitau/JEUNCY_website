import { describe, expect, it, vi, afterEach } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';

import { SkillsSection } from './SkillsSection';
import { ApiError } from '@/lib/api/client';
import { splitTags, tooLongTag } from '@/lib/tag-input';

// Retour d'etudiants du 2026-09-23 : « l'onglet competences ne veut plus
// enregistrer ». Reproduit en production : le champ affiche « Ex : React,
// Vente, Relation client… », l'etudiant tape donc sa liste separee par des
// virgules, le composant en faisait UNE competence de plus de 50 caracteres,
// le serveur repondait 400 — et l'ecran n'affichait rien.
describe('SkillsSection', () => {
  afterEach(cleanup);

  function saisir(valeur: string) {
    fireEvent.change(screen.getByLabelText('Ajouter une compétence'), {
      target: { value: valeur },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Ajouter' }));
  }

  it('découpe une saisie séparée par des virgules', async () => {
    const onSync = vi.fn().mockResolvedValue(undefined);
    render(<SkillsSection skills={[]} onSync={onSync} isSubmitting={false} />);

    saisir('Communication, Travail en equipe, Organisation');

    expect(onSync).toHaveBeenCalledWith([
      'Communication',
      'Travail en equipe',
      'Organisation',
    ]);
  });

  it('conserve les compétences déjà enregistrées et ignore les doublons', () => {
    const onSync = vi.fn().mockResolvedValue(undefined);
    render(
      <SkillsSection
        skills={[{ id: 1, name: 'Vente' }]}
        onSync={onSync}
        isSubmitting={false}
      />,
    );

    saisir('vente, Accueil, Accueil');

    expect(onSync).toHaveBeenCalledWith(['Vente', 'Accueil']);
  });

  it('explique quand une compétence dépasse la limite, sans appeler le serveur', () => {
    const onSync = vi.fn();
    render(<SkillsSection skills={[]} onSync={onSync} isSubmitting={false} />);

    saisir('Capacite a travailler en autonomie sur des projets complexes et varies');

    expect(onSync).not.toHaveBeenCalled();
    expect(screen.getByRole('alert').textContent).toContain('trop long');
  });

  it('affiche le message du serveur au lieu de rester muet', async () => {
    const onSync = vi
      .fn()
      .mockRejectedValue(
        new ApiError({ code: 'INVALID_INPUT', message: 'Refusé.' }, 400),
      );
    render(<SkillsSection skills={[]} onSync={onSync} isSubmitting={false} />);

    saisir('Vente');

    expect(await screen.findByRole('alert')).toBeTruthy();
    expect(screen.getByRole('alert').textContent).toContain('Refusé.');
  });
});

describe('splitTags', () => {
  it('nettoie espaces, vides et doublons', () => {
    expect(splitTags('  Vente ,, Accueil ;  vente  ')).toEqual(['Vente', 'Accueil']);
  });

  it('accepte une saisie simple sans virgule', () => {
    expect(splitTags('Relation client')).toEqual(['Relation client']);
  });

  it('repère ce que le serveur refusera', () => {
    expect(tooLongTag(['Vente', 'x'.repeat(51)])).toBe('x'.repeat(51));
    expect(tooLongTag(['Vente'])).toBeNull();
  });
});
