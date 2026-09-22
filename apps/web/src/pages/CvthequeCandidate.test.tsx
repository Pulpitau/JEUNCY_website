import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

import { CvthequeCandidate } from './CvthequeCandidate';
import { getCvthequeCandidate, type CvthequeCandidateDetail } from '@/lib/api/cvtheque';

vi.mock('@/lib/api/cvtheque', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/api/cvtheque')>();

  return { ...actual, getCvthequeCandidate: vi.fn() };
});

const fiche: CvthequeCandidateDetail = {
  id: 41,
  first_name: 'Léa',
  last_name_initial: 'G',
  age_band: '21-25',
  headline: 'Vendeuse en alternance',
  pitch: 'Motivée, disponible dès septembre.',
  wanted_contract_types: ['ALTERNANCE'],
  wanted_sectors: ['COMMERCE'],
  has_driving_license: true,
  driving_license_categories: ['B'],
  has_vehicle: true,
  available_from: '2026-09-01',
  skills: [{ id: 4, name: 'Vente', in_common: false }],
  software: [],
  languages: [],
  educations: [],
  experiences: [
    { title: 'Vendeuse', company: 'Zara', start_date: '2025-06-01', end_date: null },
  ],
  photo_url: null,
  has_uploaded_cv: true,
  cv_available: false,
};

function renderFiche() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/candidats/41']}>
        <Routes>
          <Route path="/candidats/:id" element={<CvthequeCandidate />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('CvthequeCandidate', () => {
  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
  });

  describe('sans candidature du candidat', () => {
    beforeEach(() => {
      vi.mocked(getCvthequeCandidate).mockResolvedValue({
        ...fiche,
        // Champs que le serveur ne renvoie plus : s'ils revenaient, la fiche
        // ne doit toujours rien en afficher. L'ancienne fiche portait un bloc
        // « coordonnées » entier (email, téléphone, LinkedIn, portfolio,
        // vidéo) : c'est lui que ces valeurs surveillent.
        phone: '0600000000',
        city: 'Perpignan',
        last_name: 'Girard',
        user: { email: 'lea@example.com' },
        linkedin_url: 'https://linkedin.com/in/lea',
        portfolio_url: 'https://lea.example.com',
        video_url: 'https://youtu.be/abc',
        cv_file_url: '/storage/cv/lea.pdf',
      } as unknown as CvthequeCandidateDetail);
    });

    it('cache le bouton de téléchargement du CV', async () => {
      renderFiche();

      await screen.findByText('Léa G.');
      expect(screen.queryByRole('button', { name: /Télécharger/ })).toBeNull();
      expect(
        screen.getByText(/Le CV est partagé dès que le candidat postule/),
      ).toBeTruthy();
    });

    it("n'affiche ni coordonnées ni ville", async () => {
      const { container } = renderFiche();

      await screen.findByText('Léa G.');
      expect(screen.queryByText(/0600000000/)).toBeNull();
      expect(screen.queryByText(/Perpignan/)).toBeNull();
      expect(screen.queryByText(/Girard/)).toBeNull();
      // Les liens sortants de l'ancien bloc « coordonnées » : ils ne se
      // voient pas dans le texte, seulement dans le href.
      expect(container.innerHTML).not.toContain('lea@example.com');
      expect(container.innerHTML).not.toContain('linkedin.com');
      expect(container.innerHTML).not.toContain('youtu.be');
      expect(container.innerHTML).not.toContain('/storage/cv/lea.pdf');
    });
  });

  describe('après une candidature', () => {
    beforeEach(() => {
      vi.mocked(getCvthequeCandidate).mockResolvedValue({
        ...fiche,
        cv_available: true,
      });
    });

    it('affiche le bouton de téléchargement du CV', async () => {
      renderFiche();

      expect(
        await screen.findByRole('button', { name: /Télécharger son CV/ }),
      ).toBeTruthy();
    });
  });
});
