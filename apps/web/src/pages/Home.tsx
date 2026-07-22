import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
  Card,
  CardHeader,
  CardTitle,
  CardDescription,
  CardContent,
} from '@/components/ui/card';

const AUDIENCES = [
  {
    title: 'Candidats',
    description:
      'Crée ton profil, génère ton CV et postule aux offres qui te correspondent.',
    tag: 'Alternants & saisonniers',
    anchor: '/a-propos#candidats',
  },
  {
    title: 'Entreprises',
    description:
      'Publie tes offres et gère tes candidatures depuis un seul tableau de bord.',
    tag: 'Recrutement',
    anchor: '/a-propos#entreprises',
  },
  {
    title: 'CFA',
    description: 'Gère tes offres multi-filières et suis le placement de tes apprenants.',
    tag: 'Formation',
    anchor: '/a-propos#cfa',
  },
];

export function Home() {
  const navigate = useNavigate();
  const [query, setQuery] = useState('');

  function handleSearch() {
    const params = query.trim() ? `?q=${encodeURIComponent(query.trim())}` : '';
    navigate(`/offres${params}`);
  }

  return (
    <main>
      <section className="mx-auto max-w-6xl px-4 py-20 text-center">
        <Badge variant="secondary" className="mb-4">
          Alternance · Saisonnier · Bénévolat
        </Badge>
        <h1 className="font-poppins text-4xl font-bold tracking-tight text-foreground md:text-6xl">
          Ton alternance commence ici.
        </h1>
        <p className="mx-auto mt-4 max-w-xl font-inter text-lg text-muted-foreground">
          Jeuncy connecte les jeunes talents aux entreprises et CFA qui recrutent, sans
          detour.
        </p>

        <div className="mx-auto mt-8 flex max-w-md flex-col gap-3 sm:flex-row">
          <Input
            placeholder="Quel métier recherches-tu ?"
            aria-label="Rechercher un métier"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === 'Enter') {
                event.preventDefault();
                handleSearch();
              }
            }}
          />
          <Button variant="gradient" className="sm:shrink-0" onClick={handleSearch}>
            Chercher une offre
          </Button>
        </div>

        <div className="mt-4 flex justify-center gap-3">
          <Button variant="outline" onClick={() => navigate('/register')}>
            Je suis une entreprise
          </Button>
        </div>
      </section>

      <section className="mx-auto max-w-6xl px-4 pb-20">
        <div className="grid gap-6 md:grid-cols-3">
          {AUDIENCES.map((audience) => (
            <Card key={audience.title}>
              <CardHeader>
                <Badge variant="outline" className="w-fit">
                  {audience.tag}
                </Badge>
                <CardTitle>{audience.title}</CardTitle>
                <CardDescription>{audience.description}</CardDescription>
              </CardHeader>
              <CardContent>
                <Link to={audience.anchor}>
                  <Button variant="secondary" size="sm">
                    En savoir plus
                  </Button>
                </Link>
              </CardContent>
            </Card>
          ))}
        </div>
      </section>
    </main>
  );
}
