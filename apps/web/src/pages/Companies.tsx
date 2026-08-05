import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Building2 } from 'lucide-react';
import { ContractType } from '@jeuncy/shared';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Card, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { WORK_MODE_LABELS } from '@/lib/work-mode-labels';
import { searchPublicCompanies } from '@/lib/api/companies';

const CONTRACT_TYPE_OPTIONS = [
  { value: '', label: 'Tous les contrats' },
  { value: ContractType.ALTERNANCE, label: 'Alternance' },
  { value: ContractType.SAISONNIER, label: 'Saisonnier' },
  { value: ContractType.BENEVOLAT, label: 'Bénévolat' },
  { value: ContractType.JOB_ETUDIANT, label: 'Job étudiant' },
  { value: ContractType.STAGE, label: 'Stage' },
];

export function Companies() {
  const [searchParams, setSearchParams] = useSearchParams();
  const name = searchParams.get('name') ?? '';
  const city = searchParams.get('city') ?? '';
  const contractType = searchParams.get('contract_type') ?? '';
  const workMode = searchParams.get('work_mode') ?? '';
  const page = Number(searchParams.get('page') ?? '1');
  const [draftName, setDraftName] = useState(name);
  const [draftCity, setDraftCity] = useState(city);

  const companiesQuery = useQuery({
    queryKey: ['companies', 'public', { name, city, contractType, workMode, page }],
    queryFn: () =>
      searchPublicCompanies({
        name: name || undefined,
        city: city || undefined,
        contract_type: (contractType as ContractType) || undefined,
        work_mode: (workMode as never) || undefined,
        page,
      }),
  });

  function applyFilters(overrides: Record<string, string>) {
    const next = new URLSearchParams(searchParams);
    const merged = {
      name: draftName,
      city: draftCity,
      contract_type: contractType,
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
    if (!('page' in overrides)) {
      next.delete('page');
    }
    setSearchParams(next);
  }

  const companies = companiesQuery.data?.data ?? [];

  return (
    <main className="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-12">
      <div>
        <h1 className="font-poppins text-3xl font-bold">Les entreprises</h1>
        <p className="mt-1 font-inter text-muted-foreground">
          Découvre les entreprises qui recrutent sur Jeuncy.
        </p>
      </div>

      <form
        onSubmit={(event) => {
          event.preventDefault();
          applyFilters({});
        }}
        className="flex flex-col gap-3 rounded-md border border-border p-4 sm:flex-row sm:flex-wrap sm:items-end"
      >
        <div className="flex flex-1 flex-col gap-2">
          <Label htmlFor="search-name">Nom</Label>
          <Input
            id="search-name"
            placeholder="NexaTech…"
            value={draftName}
            onChange={(event) => setDraftName(event.target.value)}
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
          <Label htmlFor="search-work-mode">Mode de travail</Label>
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

      {companiesQuery.isLoading ? (
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      ) : companiesQuery.isError ? (
        <p role="alert" className="font-inter text-sm text-destructive">
          Impossible de charger les entreprises pour le moment, réessaie plus tard.
        </p>
      ) : companies.length === 0 ? (
        <p className="font-inter text-sm text-muted-foreground">
          Aucune entreprise ne correspond à ta recherche pour l'instant.
        </p>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {companies.map((company) => (
            <Link key={company.id} to={`/entreprises/${company.id}`}>
              <Card className="h-full transition-all duration-200 hover:-translate-y-1 hover:border-primary hover:shadow-lg">
                <CardHeader>
                  <div className="flex items-center gap-3">
                    {company.logo_url ? (
                      <img
                        src={company.logo_url}
                        alt=""
                        className="h-10 w-10 shrink-0 rounded-md border border-border object-contain p-0.5"
                      />
                    ) : (
                      <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-border bg-muted">
                        <Building2
                          className="h-4 w-4 text-muted-foreground"
                          aria-hidden="true"
                        />
                      </div>
                    )}
                    <div>
                      <CardTitle>{company.name}</CardTitle>
                      {company.city && <CardDescription>{company.city}</CardDescription>}
                    </div>
                  </div>
                </CardHeader>
              </Card>
            </Link>
          ))}
        </div>
      )}
    </main>
  );
}
