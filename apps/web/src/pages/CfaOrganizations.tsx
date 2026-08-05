import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { GraduationCap } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Card, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { WORK_MODE_LABELS } from '@/lib/work-mode-labels';
import { DIPLOMA_LEVEL_OPTIONS } from '@/lib/diploma-level-options';
import { searchPublicCfaOrganizations } from '@/lib/api/cfa-organizations';

export function CfaOrganizations() {
  const [searchParams, setSearchParams] = useSearchParams();
  const name = searchParams.get('name') ?? '';
  const city = searchParams.get('city') ?? '';
  const diplomaLevel = searchParams.get('diploma_level') ?? '';
  const trainingMode = searchParams.get('training_mode') ?? '';
  const page = Number(searchParams.get('page') ?? '1');
  const [draftName, setDraftName] = useState(name);
  const [draftCity, setDraftCity] = useState(city);

  const cfaQuery = useQuery({
    queryKey: [
      'cfa-organizations',
      'public',
      { name, city, diplomaLevel, trainingMode, page },
    ],
    queryFn: () =>
      searchPublicCfaOrganizations({
        name: name || undefined,
        city: city || undefined,
        diploma_level: diplomaLevel || undefined,
        training_mode: (trainingMode as never) || undefined,
        page,
      }),
  });

  function applyFilters(overrides: Record<string, string>) {
    const next = new URLSearchParams(searchParams);
    const merged = {
      name: draftName,
      city: draftCity,
      diploma_level: diplomaLevel,
      training_mode: trainingMode,
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

  const cfaOrganizations = cfaQuery.data?.data ?? [];

  return (
    <main className="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-12">
      <div>
        <h1 className="font-poppins text-3xl font-bold">Les CFA</h1>
        <p className="mt-1 font-inter text-muted-foreground">
          Découvre les CFA partenaires qui recrutent des alternants sur Jeuncy.
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
            placeholder="CFA Sup Alternance…"
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
          <Label htmlFor="search-diploma-level">Niveau de diplôme</Label>
          <select
            id="search-diploma-level"
            className={cn(
              'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm font-inter focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
            )}
            value={diplomaLevel}
            onChange={(event) => applyFilters({ diploma_level: event.target.value })}
          >
            <option value="">Tous les niveaux</option>
            {DIPLOMA_LEVEL_OPTIONS.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
        </div>
        <div className="flex flex-1 flex-col gap-2">
          <Label htmlFor="search-training-mode">Mode de formation</Label>
          <select
            id="search-training-mode"
            className={cn(
              'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm font-inter focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
            )}
            value={trainingMode}
            onChange={(event) => applyFilters({ training_mode: event.target.value })}
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

      {cfaQuery.isLoading ? (
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      ) : cfaQuery.isError ? (
        <p role="alert" className="font-inter text-sm text-destructive">
          Impossible de charger les CFA pour le moment, réessaie plus tard.
        </p>
      ) : cfaOrganizations.length === 0 ? (
        <p className="font-inter text-sm text-muted-foreground">
          Aucun CFA ne correspond à ta recherche pour l'instant.
        </p>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {cfaOrganizations.map((cfa) => (
            <Link key={cfa.id} to={`/cfa/${cfa.id}`}>
              <Card className="h-full transition-all duration-200 hover:-translate-y-1 hover:border-primary hover:shadow-lg">
                <CardHeader>
                  <div className="flex items-center gap-3">
                    {cfa.logo_url ? (
                      <img
                        src={cfa.logo_url}
                        alt=""
                        className="h-10 w-10 shrink-0 rounded-md border border-border object-contain p-0.5"
                      />
                    ) : (
                      <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-border bg-muted">
                        <GraduationCap
                          className="h-4 w-4 text-muted-foreground"
                          aria-hidden="true"
                        />
                      </div>
                    )}
                    <div>
                      <CardTitle>{cfa.name}</CardTitle>
                      {cfa.city && <CardDescription>{cfa.city}</CardDescription>}
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
