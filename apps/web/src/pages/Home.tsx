import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getPublicOfferCount } from '@/lib/api/job-offers';
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
import { FreeForCandidatesBadge } from '@/components/FreeForCandidatesBadge';
import { usePageMetadata } from '@/hooks/use-page-metadata';

const AUDIENCES = [
  {
    title: 'Candidats',
    description:
      'Crée ton profil, génère ton CV et postule aux offres qui te correspondent.',
    tag: 'Gratuit, à vie',
    anchor: '/a-propos#candidats',
  },
  {
    title: 'Entreprises',
    description:
      'Publiez vos offres, recevez les candidatures et cherchez dans la CVthèque. Sans rien payer.',
    tag: '100 % gratuit',
    anchor: '/tarifs',
  },
  // Les CFA ne s'inscrivent plus seuls (2026-09-15) : la carte reste, pour
  // ne pas laisser croire que les ecoles sont absentes de Jeuncy, mais elle
  // renvoie vers un echange plutot que vers une inscription.
  {
    title: 'CFA partenaires',
    description:
      "Jeuncy travaille avec des écoles partenaires sélectionnées, pour accompagner les jeunes jusqu'au contrat.",
    tag: 'Sur invitation',
    anchor: '/contact',
  },
];

export function Home() {
  usePageMetadata(
    'Alternance, jobs saisonniers et étudiants',
    'Trouve ton alternance, ton job saisonnier, ton stage ou ta mission bénévole. Gratuit pour les candidats comme pour les entreprises.',
    '/',
  );
  const navigate = useNavigate();
  const [query, setQuery] = useState('');
  // Le VRAI nombre d'offres en ligne, en direct — jamais un chiffre rond
  // ecrit a la main qui serait faux le lendemain.
  const countQuery = useQuery({
    queryKey: ['job-offers', 'count'],
    queryFn: getPublicOfferCount,
  });
  const offerCount = countQuery.data?.total ?? 0;

  function handleSearch() {
    const params = query.trim() ? `?q=${encodeURIComponent(query.trim())}` : '';
    navigate(`/offres${params}`);
  }

  return (
    <main>
      <section className="relative">
        {/* Pas de glow/blob decoratif en fond ici : plusieurs tentatives
            (blur+overflow-hidden, puis degrade radial) ont toutes fini par
            montrer une coupure visible selon la fenetre/le navigateur. Le
            texte en degrade + les cartes/boutons plus vifs suffisent a
            l'effet "jeune et dynamique" sans ce risque. */}
        <div className="relative mx-auto max-w-6xl px-4 py-20 text-center">
          <Badge
            variant="secondary"
            className="animate-in fade-in slide-in-from-bottom-2 mb-4 duration-500"
          >
            Alternance · Saisonnier · Bénévolat · Job étudiant
          </Badge>
          <h1 className="animate-in fade-in slide-in-from-bottom-3 font-poppins text-4xl font-bold tracking-tight text-foreground duration-700 md:text-6xl">
            Match ton <span className="text-jeuncy-coral">alternance</span>
          </h1>
          <p className="animate-in fade-in slide-in-from-bottom-3 mx-auto mt-4 max-w-xl font-inter text-lg text-muted-foreground duration-700 [animation-delay:100ms] [animation-fill-mode:backwards]">
            Jeuncy connecte les jeunes talents aux entreprises et CFA qui recrutent, sans
            detour.
          </p>

          {offerCount > 0 && (
            <Link
              to="/offres"
              className="animate-in fade-in zoom-in-95 mx-auto mt-6 inline-flex items-baseline gap-2 rounded-full border border-jeuncy-coral/30 bg-card px-6 py-3 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-jeuncy-coral hover:shadow-md [animation-delay:150ms] [animation-fill-mode:backwards]"
            >
              <span className="bg-jeuncy-gradient bg-clip-text font-poppins text-3xl font-bold text-transparent md:text-4xl">
                {offerCount.toLocaleString('fr-FR')}
              </span>
              <span className="font-inter text-base text-foreground">
                offres d'alternance disponibles aujourd'hui
              </span>
            </Link>
          )}

          <div className="animate-in fade-in slide-in-from-bottom-3 mx-auto mt-8 flex max-w-md flex-col gap-3 duration-700 [animation-delay:200ms] [animation-fill-mode:backwards] sm:flex-row">
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
            <Button
              variant="gradient"
              className="transition-transform duration-200 hover:scale-105 sm:shrink-0"
              onClick={handleSearch}
            >
              Chercher une offre
            </Button>
          </div>

          <div className="animate-in fade-in slide-in-from-bottom-3 mt-4 flex justify-center gap-3 duration-700 [animation-delay:300ms] [animation-fill-mode:backwards]">
            <Button variant="outline" onClick={() => navigate('/register?role=COMPANY')}>
              Je suis une entreprise — c'est gratuit
            </Button>
          </div>

          <div className="animate-in fade-in slide-in-from-bottom-3 mt-6 flex justify-center duration-700 [animation-delay:400ms] [animation-fill-mode:backwards]">
            <FreeForCandidatesBadge />
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-6xl px-4 pb-20">
        <div className="grid gap-6 md:grid-cols-3">
          {AUDIENCES.map((audience, index) => (
            <Card
              key={audience.title}
              className="animate-in fade-in slide-in-from-bottom-3 transition-all duration-300 [animation-fill-mode:backwards] hover:-translate-y-1 hover:shadow-lg"
              style={{ animationDelay: `${index * 100}ms` }}
            >
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
