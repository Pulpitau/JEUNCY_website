import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Card, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { searchPublicCompanies } from '@/lib/api/companies';

export function Companies() {
  const [searchParams, setSearchParams] = useSearchParams();
  const city = searchParams.get('city') ?? '';
  const page = Number(searchParams.get('page') ?? '1');
  const [draftCity, setDraftCity] = useState(city);

  const companiesQuery = useQuery({
    queryKey: ['companies', 'public', { city, page }],
    queryFn: () => searchPublicCompanies(city || undefined),
  });

  function applyFilters(overrides: Record<string, string>) {
    const next = new URLSearchParams(searchParams);
    const merged = { city: draftCity, ...overrides };
    Object.entries(merged).forEach(([key, value]) => {
      if (value) {
        next.set(key, value);
      } else {
        next.delete(key);
      }
    });
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
        className="flex flex-col gap-3 rounded-md border border-border p-4 sm:flex-row sm:items-end"
      >
        <div className="flex flex-1 flex-col gap-2">
          <Label htmlFor="search-city">Ville</Label>
          <Input
            id="search-city"
            placeholder="Rennes, Nantes…"
            value={draftCity}
            onChange={(event) => setDraftCity(event.target.value)}
          />
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
                  <CardTitle>{company.name}</CardTitle>
                  {company.city && <CardDescription>{company.city}</CardDescription>}
                </CardHeader>
              </Card>
            </Link>
          ))}
        </div>
      )}
    </main>
  );
}
