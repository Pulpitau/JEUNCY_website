import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { UserRole } from '@jeuncy/shared';

import { Profile } from './Profile';
import { getMyProfile, createProfile } from '@/lib/api/candidate-profile';
import { ApiError } from '@/lib/api/client';
import { useAuthStore } from '@/store/auth-store';

// Bug signale par Pierre le 2026-09-23 : un candidat remplit son profil, ouvre
// une autre page sans enregistrer, revient — et retrouve un formulaire vide.
// Ces tests montent puis DEMONTENT la page, comme le fait une navigation React
// Router, et verifient ce qu'on retrouve au retour.
vi.mock('@/lib/api/candidate-profile', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/api/candidate-profile')>();

  return {
    ...actual,
    getMyProfile: vi.fn(),
    createProfile: vi.fn(),
    listGeneratedCvs: vi.fn(),
  };
});

function renderProfile() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <Profile />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

async function saisirIdentite(prenom: string, nom: string) {
  const champPrenom = await screen.findByLabelText('Prénom');
  fireEvent.change(champPrenom, { target: { value: prenom } });
  fireEvent.change(screen.getByLabelText('Nom'), { target: { value: nom } });
}

function valeur(label: string): string {
  return (screen.getByLabelText(label) as HTMLInputElement).value;
}

describe('Profile — brouillon local', () => {
  beforeEach(() => {
    window.sessionStorage.clear();
    useAuthStore.setState({
      user: { id: '7', email: 'lea@example.com', role: UserRole.CANDIDATE },
      accessToken: 'jeton',
      isLoading: false,
    });
    // Un candidat qui n'a pas encore de profil : c'est le cas ou la perte de
    // saisie fait le plus mal (tout le formulaire est a remplir).
    vi.mocked(getMyProfile).mockRejectedValue(
      new ApiError({ code: 'PROFILE_NOT_FOUND', message: 'Profil introuvable.' }, 404),
    );
  });

  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
    window.sessionStorage.clear();
  });

  it('retrouve la saisie apres un aller-retour vers une autre page', async () => {
    const { unmount } = renderProfile();
    await saisirIdentite('Léa', 'Girard');

    // Navigation vers une offre : React Router demonte la page.
    unmount();
    cleanup();

    renderProfile();
    await screen.findByLabelText('Prénom');

    expect(valeur('Prénom')).toBe('Léa');
    expect(valeur('Nom')).toBe('Girard');
  });

  it('previent que la saisie a ete restauree et sait la jeter', async () => {
    const { unmount } = renderProfile();
    await saisirIdentite('Léa', 'Girard');
    unmount();
    cleanup();

    renderProfile();
    await screen.findByLabelText('Prénom');
    expect(screen.getByRole('status')).toBeTruthy();

    fireEvent.click(screen.getByRole('button', { name: 'Vider le formulaire' }));
    expect(valeur('Prénom')).toBe('');

    // Et le brouillon ne revient pas au passage suivant.
    cleanup();
    renderProfile();
    await screen.findByLabelText('Prénom');
    expect(valeur('Prénom')).toBe('');
    expect(screen.queryByRole('status')).toBeNull();
  });

  it('oublie le brouillon une fois le profil enregistre', async () => {
    vi.mocked(createProfile).mockResolvedValue({ id: 1 } as never);

    renderProfile();
    await saisirIdentite('Léa', 'Girard');
    fireEvent.change(screen.getByLabelText(/Date de naissance/), {
      target: { value: '2006-04-12' },
    });

    fireEvent.click(screen.getByRole('button', { name: 'Créer mon profil' }));
    await vi.waitFor(() => expect(vi.mocked(createProfile)).toHaveBeenCalled());

    await vi.waitFor(() =>
      expect(window.sessionStorage.getItem('jeuncy.profil-brouillon.7')).toBeNull(),
    );
  });

  it('ne restaure jamais le brouillon d’un autre compte', async () => {
    const { unmount } = renderProfile();
    await saisirIdentite('Léa', 'Girard');
    unmount();
    cleanup();

    // Deconnexion puis autre candidat sur le meme poste : clearSession vide
    // les brouillons (store/auth-store.ts).
    useAuthStore.getState().clearSession();
    useAuthStore.setState({
      user: { id: '8', email: 'malik@example.com', role: UserRole.CANDIDATE },
      accessToken: 'jeton',
      isLoading: false,
    });

    renderProfile();
    await screen.findByLabelText('Prénom');

    expect(valeur('Prénom')).toBe('');
    expect(valeur('Nom')).toBe('');
  });
});
