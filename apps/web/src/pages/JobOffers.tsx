import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { ContractType, type WorkMode } from '@jeuncy/shared';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { WORK_MODE_LABELS } from '@/lib/work-mode-labels';
import { searchPublicOffers } from '@/lib/api/job-offers';
import { PublicJobOfferCard } from '@/components/features/job-offers/PublicJobOfferCard';
import { FreeForCandidatesBadge } from '@/components/FreeForCandidatesBadge';
import { usePageMetadata } from '@/hooks/use-page-metadata';

const CONTRACT_TYPE_OPTIONS = [
  { value: '', label: 'Tous les contrats' },
  { value: ContractType.ALTERNANCE, label: 'Alternance' },
  { value: ContractType.SAISONNIER, label: 'Saisonnier' },
  { value: ContractType.BENEVOLAT, label: 'Bénévolat' },
  { value: ContractType.JOB_ETUDIANT, label: 'Job étudiant' },
  { value: ContractType.STAGE, label: 'Stage' },
];

export function JobOffers() {
  usePageMetadata(
    "Offres d'alternance et jobs",
    "Toutes les offres d'alternance, jobs saisonniers, jobs étudiants, stages et missions bénévoles. Filtre par ville et par contrat.",
    '/offres',
  );
  const [searchParams, setSearchParams] = useSearchParams();
  const q = searchParams.get('q') ?? '';
  const contractType = searchParams.get('contract_type') ?? '';
  const city = searchParams.get('city') ?? '';
  const workMode = searchParams.get('work_mode') ?? '';
  const page = Number(searchParams.get('page') ?? '1');

  const [draftQ, setDraftQ] = useState(q);
  const [draftCity, setDraftCity] = useState(city);

  const offersQuery = useQuery({
    queryKey: ['job-offers', 'public', { q, contractType, city, workMode, page }],
    queryFn: () =>
      searchPublicOffers({
        q: q || undefined,
        contract_type: (contractType as ContractType) || undefined,
        city: city || undefined,
        work_mode: (workMode as WorkMode) || undefined,
        page,
      }),
  });

  function applyFilters(overrides: Record<string, string>) {
    const next = new URLSearchParams(searchParams);
    const merged = {
      q: draftQ,
      contract_type: contractType,
      city: draftCity,
      work_mode: workMode,
      ...overrides,
    };
    Object.entries(merged).forEach(([key, value]) => {
      if (value) {
        next.set(key, value);
      } else {
        next.delete(key);
      }
    });
    // Un changement de filtre repart de la page 1 ; la pagination passe "page"
    // explicitement dans overrides et ne doit pas se faire ecraser ici.
    if (!('page' in overrides)) {
      next.delete('page');
    }
    setSearchParams(next);
  }

  const offers = offersQuery.data?.data ?? [];
  const lastPage = offersQuery.data?.last_page ?? 1;

  return (
    <main className="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-12">
      <div className="flex flex-col gap-3">
        <div>
          <h1 className="font-poppins text-3xl font-bold">Les offres</h1>
          <p className="mt-1 font-inter text-muted-foreground">
            Alternance, saisonnier, bénévolat, job étudiant — trouve l'offre qui te
            correspond.
          </p>
        </div>
        <FreeForCandidatesBadge className="self-start" />
      </div>

      <form
        onSubmit={(event) => {
          event.preventDefault();
          applyFilters({});
        }}
        className="flex flex-col gap-3 rounded-md border border-border p-4 sm:flex-row sm:items-end"
      >
        <div className="flex flex-1 flex-col gap-2">
          <Label htmlFor="search-q">Mot-clé</Label>
          <Input
            id="search-q"
            placeholder="Quel métier recherches-tu ?"
            value={draftQ}
            onChange={(event) => setDraftQ(event.target.value)}
          />
        </div>
        <div className="flex flex-1 flex-col gap-2">
          <Label htmlFor="search-city">Ville</Label>
          <Input
            id="search-city"
            placeholder="Rennes, Nantes…"
            value={draftCity}
            onChange={(event) => setDraftCity(event.target.value)}
          />
        </div>
        <div className="flex flex-1 flex-col gap-2">
          <Label htmlFor="search-contract-type">Type de contrat</Label>
          <select
            id="search-contract-type"
            className={cn(
              'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm font-inter focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
            )}
            value={contractType}
            onChange={(event) => applyFilters({ contract_type: event.target.value })}
          >
            {CONTRACT_TYPE_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </div>
        <div className="flex flex-1 flex-col gap-2">
          <Label htmlFor="search-work-mode">Type d'offre</Label>
          <select
            id="search-work-mode"
            className={cn(
              'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm font-inter focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
            )}
            value={workMode}
            onChange={(event) => applyFilters({ work_mode: event.target.value })}
          >
            <option value="">Tous les modes</option>
            {Object.entries(WORK_MODE_LABELS).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </div>
        <Button type="submit" variant="gradient">
          Rechercher
        </Button>
      </form>

      {offersQuery.isLoading ? (
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      ) : offersQuery.isError ? (
        <p role="alert" className="font-inter text-sm text-destructive">
          Impossible de charger les offres pour le moment, réessaie plus tard.
        </p>
      ) : offers.length === 0 ? (
        <p className="font-inter text-sm text-muted-foreground">
          Aucune offre ne correspond à ta recherche pour l'instant.
        </p>
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {offers.map((offer) => (
              <PublicJobOfferCard key={offer.id} offer={offer} />
            ))}
          </div>

          {lastPage > 1 && (
            <div className="flex items-center justify-center gap-3">
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={page <= 1}
                onClick={() => applyFilters({ page: String(page - 1) })}
              >
                Précédent
              </Button>
              <span className="font-inter text-sm text-muted-foreground">
                Page {page} / {lastPage}
              </span>
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={page >= lastPage}
                onClick={() => applyFilters({ page: String(page + 1) })}
              >
                Suivant
              </Button>
            </div>
          )}
        </>
      )}
    </main>
  );
}
