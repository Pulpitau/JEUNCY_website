import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

import { Cvtheque } from './Cvtheque';
import { searchCvtheque, type CandidateCard } from '@/lib/api/cvtheque';

vi.mock('@/lib/api/cvtheque', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/api/cvtheque')>();

  return { ...actual, searchCvtheque: vi.fn() };
});

// Ce que la CVtheque ne doit PLUS montrer.
//
// Le serveur ne transmet plus le nom complet ni la ville (la carte n'a pas
// ces cles), mais un test qui se contenterait de le croire ne prouverait
// rien : si un jour le presenteur regresse et les renvoie, c'est ici qu'on
// doit s'en apercevoir. On fournit donc au composant une reponse « polluee »
// et on verifie qu'il n'en affiche rien.
const carte: CandidateCard = {
  id: 41,
  first_name: 'Léa',
  last_name_initial: 'G',
  age_band: '18-20',
  headline: 'Vendeuse en alternance',
  pitch: 'Motivée, disponible dès septembre.',
  wanted_contract_types: ['ALTERNANCE'],
  wanted_sectors: ['COMMERCE'],
  has_driving_license: true,
  driving_license_categories: ['B'],
  has_vehicle: false,
  available_from: '2026-09-01',
  skills: [{ id: 4, name: 'Vente', in_common: false }],
  software: [],
  languages: [{ name: 'Anglais', level: 'B1' }],
  educations: [],
  experiences: [],
  photo_url: null,
  has_uploaded_cv: true,
};

const cartePolluee = {
  ...carte,
  city: 'Perpignan',
  last_name: 'Girard',
  age: 19,
} as CandidateCard;

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/candidats']}>
        <Cvtheque />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('Cvtheque', () => {
  beforeEach(() => {
    vi.mocked(searchCvtheque).mockResolvedValue({
      data: [cartePolluee],
      current_page: 1,
      last_page: 1,
      total: 1,
    });
  });

  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
  });

  it("montre le prénom et l'initiale, jamais le nom complet", async () => {
    renderPage();

    expect(await screen.findByText('Léa G.')).toBeTruthy();
    expect(screen.queryByText(/Girard/)).toBeNull();
  });

  it('ne rend aucune ville de résidence', async () => {
    renderPage();

    await screen.findByText('Léa G.');
    expect(screen.queryByText(/Perpignan/)).toBeNull();
  });

  it("montre une tranche d'âge et non un âge exact", async () => {
    renderPage();

    expect(await screen.findByText('18 à 20 ans')).toBeTruthy();
    expect(screen.queryByText(/^\d+ ans$/)).toBeNull();
  });

  it("n'offre plus de filtre par ville", async () => {
    renderPage();

    await screen.findByText('Léa G.');
    expect(screen.queryByPlaceholderText('Ville')).toBeNull();
    expect(screen.queryByLabelText('Ville')).toBeNull();
  });

  it('affiche le permis depuis la colonne structurée', async () => {
    renderPage();

    expect(await screen.findByText(/Permis B/)).toBeTruthy();
  });
});
