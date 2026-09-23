import { describe, expect, it, vi, afterEach } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';

import { ExperienceSection } from './ExperienceSection';
import { ApiError } from '@/lib/api/client';
import type { Experience } from '@/lib/api/candidate-profile';

// Demande d'un etudiant (2026-09-23) : pouvoir corriger une experience deja
// saisie, pas seulement la supprimer et tout retaper.
const experience = {
  id: 42,
  candidate_profile_id: 7,
  title: 'Vendeuse',
  company: 'Boulangerie du port',
  location: 'Perpignan',
  // Le serveur renvoie des dates ISO completes : le formulaire doit les
  // ramener en AAAA-MM-JJ, sinon l'input type="date" reste vide.
  start_date: '2025-06-01T00:00:00.000000Z',
  end_date: null,
  description: 'Accueil et encaissement.',
} as unknown as Experience;

function renderSection(overrides: Record<string, unknown> = {}) {
  const props = {
    experiences: [experience],
    onAdd: vi.fn().mockResolvedValue(undefined),
    onUpdate: vi.fn().mockResolvedValue(undefined),
    onDelete: vi.fn().mockResolvedValue(undefined),
    isSubmitting: false,
    ...overrides,
  };

  render(<ExperienceSection {...(props as never)} />);

  return props;
}

describe('ExperienceSection', () => {
  afterEach(cleanup);

  it('pré-remplit le formulaire avec l’expérience à corriger', () => {
    renderSection();

    fireEvent.click(screen.getByRole('button', { name: 'Modifier' }));

    expect((screen.getByLabelText('Intitulé') as HTMLInputElement).value).toBe(
      'Vendeuse',
    );
    expect((screen.getByLabelText('Entreprise') as HTMLInputElement).value).toBe(
      'Boulangerie du port',
    );
    expect((screen.getByLabelText('Début') as HTMLInputElement).value).toBe('2025-06-01');
    expect(screen.getByRole('button', { name: 'Enregistrer' })).toBeTruthy();
  });

  it('envoie la modification sur l’identifiant existant', async () => {
    const props = renderSection();

    fireEvent.click(screen.getByRole('button', { name: 'Modifier' }));
    fireEvent.change(screen.getByLabelText('Intitulé'), {
      target: { value: 'Vendeuse en boulangerie' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await vi.waitFor(() => expect(props.onUpdate).toHaveBeenCalled());
    expect(props.onUpdate).toHaveBeenCalledWith(
      42,
      expect.objectContaining({ title: 'Vendeuse en boulangerie' }),
    );
    expect(props.onAdd).not.toHaveBeenCalled();
  });

  it('repart d’un formulaire vide pour un ajout après une modification', () => {
    renderSection();

    fireEvent.click(screen.getByRole('button', { name: 'Modifier' }));
    fireEvent.click(screen.getByRole('button', { name: 'Annuler' }));
    fireEvent.click(screen.getByRole('button', { name: '+ Ajouter une expérience' }));

    expect((screen.getByLabelText('Intitulé') as HTMLInputElement).value).toBe('');
    expect(screen.getByRole('button', { name: 'Ajouter' })).toBeTruthy();
  });

  it('affiche le refus du serveur au lieu de rester muet', async () => {
    const props = renderSection({
      onUpdate: vi
        .fn()
        .mockRejectedValue(
          new ApiError({ code: 'INVALID_INPUT', message: 'Date de fin invalide.' }, 400),
        ),
    });

    fireEvent.click(screen.getByRole('button', { name: 'Modifier' }));
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await vi.waitFor(() => expect(props.onUpdate).toHaveBeenCalled());
    expect((await screen.findByRole('alert')).textContent).toContain(
      'Date de fin invalide.',
    );
  });
});
