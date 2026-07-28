import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Card, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { searchPublicCfaOrganizations } from '@/lib/api/cfa-organizations';

export function CfaOrganizations() {
  const [searchParams, setSearchParams] = useSearchParams();
  const city = searchParams.get('city') ?? '';
  const page = Number(searchParams.get('page') ?? '1');
  const [draftCity, setDraftCity] = useState(city);

  const cfaQuery = useQuery({
    queryKey: ['cfa-organizations', 'public', { city, page }],
    queryFn: () => searchPublicCfaOrganizations(city || undefined),
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
                  <CardTitle>{cfa.name}</CardTitle>
                  {cfa.city && <CardDescription>{cfa.city}</CardDescription>}
                </CardHeader>
              </Card>
            </Link>
          ))}
        </div>
      )}
    </main>
  );
}
