import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import {
  Search,
  MapPin,
  Car,
  Lock,
  Sparkles,
  Languages as LanguagesIcon,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
  searchCvtheque,
  type CvthequeCandidate,
  type CvthequeSearchFilters,
} from '@/lib/api/cvtheque';
import {
  getFounderOffer,
  SUBSCRIPTION_PRICE_LABEL,
  FOUNDER_SUBSCRIPTION_PRICE_LABEL,
} from '@/lib/api/subscriptions';
import { ApiError } from '@/lib/api/client';

// Etat de recherche porte par l'URL (comme JobOffers.tsx) : un recruteur peut
// mettre une recherche en favori ou la partager a un collegue.
function filtersFromParams(params: URLSearchParams): CvthequeSearchFilters {
  return {
    q: params.get('q') ?? undefined,
    city: params.get('city') ?? undefined,
    language: params.get('language') ?? undefined,
    driving_license: params.get('driving_license') === '1' || undefined,
    skills: params.getAll('skills').filter(Boolean),
    page: Number(params.get('page') ?? '1') || 1,
  };
}

function initials(candidate: CvthequeCandidate): string {
  return `${candidate.first_name.charAt(0)}${candidate.last_name.charAt(0)}`.toUpperCase();
}

// Ecran affiche a une entreprise/CFA sans abonnement. Volontairement vendeur —
// c'est le principal point de conversion de la CVtheque — mais SANS afficher le
// moindre profil, meme floute : montrer de vraies donnees personnelles a
// quelqu'un qui n'y a pas droit serait exactement ce que la garde serveur
// empeche.
function SubscriptionGate() {
  const founderOfferQuery = useQuery({
    queryKey: ['founder-offer'],
    queryFn: getFounderOffer,
  });
  const founderOffer = founderOfferQuery.data ?? null;

  return (
    <Card className="mx-auto max-w-2xl overflow-hidden border-2 border-primary/40">
      <div className="h-1 bg-jeuncy-gradient" />
      <CardContent className="flex flex-col items-center gap-4 p-8 text-center">
        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-jeuncy-gradient text-white">
          <Lock className="h-6 w-6" aria-hidden="true" />
        </div>
        <h2 className="font-poppins text-2xl font-bold text-foreground">
          La CVthèque est réservée aux abonnés
        </h2>
        <p className="max-w-md font-inter text-sm text-muted-foreground">
          N'attendez plus les candidatures. Filtrez les profils par compétence, ville,
          langue ou permis, consultez leur parcours complet et contactez-les directement.
        </p>

        {founderOffer?.available && (
          <div className="w-full rounded-md border border-jeuncy-orange/40 bg-jeuncy-orange/10 px-4 py-3">
            <div className="flex items-center justify-center gap-2">
              <Sparkles className="h-4 w-4 text-jeuncy-orange" aria-hidden="true" />
              <span className="font-poppins text-sm font-semibold text-foreground">
                Offre d'ouverture — il reste {founderOffer.seats_remaining} place
                {founderOffer.seats_remaining > 1 ? 's' : ''}
              </span>
            </div>
            <p className="mt-1 font-inter text-sm text-muted-foreground">
              <span className="line-through">{SUBSCRIPTION_PRICE_LABEL}</span>{' '}
              <span className="font-poppins font-semibold text-foreground">
                {FOUNDER_SUBSCRIPTION_PRICE_LABEL}
              </span>
              /mois, conservés tant que votre abonnement continue.
            </p>
          </div>
        )}

        <Link to="/tarifs">
          <Button variant="gradient" size="lg">
            Découvrir l'abonnement
          </Button>
        </Link>
      </CardContent>
    </Card>
  );
}

function CandidateCard({ candidate }: { candidate: CvthequeCandidate }) {
  return (
    // min-w-0 indispensable : un enfant de grille a min-width:auto par defaut
    // et refuse de descendre sous la largeur intrinseque de son contenu. Sans
    // lui, la carte faisait 460px sur un ecran de 375 et toute la page
    // debordait lateralement (mesure : scrollWidth 476 pour clientWidth 375).
    // Les truncate internes ne suffisent pas — ils operent DANS la largeur
    // allouee, ils ne la contraignent pas.
    <Link
      to={`/candidats/${candidate.id}`}
      className="group flex min-w-0 flex-col gap-3 rounded-lg border border-border bg-card p-5 transition-all duration-200 hover:-translate-y-0.5 hover:border-primary hover:shadow-md"
    >
      <div className="flex items-center gap-3">
        {candidate.photo_url ? (
          <img
            src={candidate.photo_url}
            alt=""
            className="h-12 w-12 shrink-0 rounded-full object-cover"
          />
        ) : (
          <span
            aria-hidden="true"
            className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-jeuncy-gradient font-poppins text-sm font-bold text-white"
          >
            {initials(candidate)}
          </span>
        )}
        <div className="min-w-0">
          <p className="truncate font-poppins font-semibold text-foreground">
            {candidate.first_name} {candidate.last_name}
          </p>
          {candidate.headline && (
            <p className="truncate font-inter text-sm text-muted-foreground">
              {candidate.headline}
            </p>
          )}
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-x-3 gap-y-1 font-inter text-xs text-muted-foreground">
        {candidate.city && (
          <span className="inline-flex items-center gap-1">
            <MapPin className="h-3.5 w-3.5" aria-hidden="true" />
            {candidate.city}
          </span>
        )}
        {candidate.driving_license && (
          <span className="inline-flex items-center gap-1">
            <Car className="h-3.5 w-3.5" aria-hidden="true" />
            {candidate.driving_license}
          </span>
        )}
        {candidate.languages.length > 0 && (
          <span className="inline-flex items-center gap-1">
            <LanguagesIcon className="h-3.5 w-3.5" aria-hidden="true" />
            {candidate.languages.map((l) => l.name).join(', ')}
          </span>
        )}
      </div>

      {candidate.skills.length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {candidate.skills.slice(0, 6).map((skill) => (
            <Badge key={skill.id} variant="secondary" className="text-xs">
              {skill.name}
            </Badge>
          ))}
          {candidate.skills.length > 6 && (
            <Badge variant="outline" className="text-xs">
              +{candidate.skills.length - 6}
            </Badge>
          )}
        </div>
      )}
    </Link>
  );
}

export function Cvtheque() {
  const [searchParams, setSearchParams] = useSearchParams();
  const filters = filtersFromParams(searchParams);

  // Champs pilotes localement puis pousses dans l'URL a la soumission : eviter
  // une requete a chaque frappe.
  const [q, setQ] = useState(filters.q ?? '');
  const [city, setCity] = useState(filters.city ?? '');
  const [language, setLanguage] = useState(filters.language ?? '');
  const [hasLicense, setHasLicense] = useState(Boolean(filters.driving_license));

  const query = useQuery({
    queryKey: ['cvtheque', searchParams.toString()],
    queryFn: () => searchCvtheque(filters),
    // Un 402 signifie "il faut s'abonner", pas une panne : inutile de reessayer.
    retry: (failureCount, error) =>
      !(error instanceof ApiError && error.status === 402) && failureCount < 3,
  });

  function applyFilters(event: React.FormEvent) {
    event.preventDefault();
    const next = new URLSearchParams();
    if (q.trim()) next.set('q', q.trim());
    if (city.trim()) next.set('city', city.trim());
    if (language.trim()) next.set('language', language.trim());
    if (hasLicense) next.set('driving_license', '1');
    setSearchParams(next);
  }

  function goToPage(page: number) {
    const next = new URLSearchParams(searchParams);
    next.set('page', String(page));
    setSearchParams(next);
  }

  const needsSubscription =
    query.isError && query.error instanceof ApiError && query.error.status === 402;

  return (
    <main className="mx-auto max-w-6xl px-4 py-10">
      <div className="mb-8">
        <Badge variant="secondary" className="mb-3">
          CVthèque
        </Badge>
        <h1 className="font-poppins text-3xl font-bold text-foreground md:text-4xl">
          Trouvez vos futurs{' '}
          <span className="bg-jeuncy-gradient bg-clip-text text-transparent">
            talents
          </span>
        </h1>
        <p className="mt-2 max-w-2xl font-inter text-muted-foreground">
          Recherchez directement dans les profils des candidats, sans attendre qu'ils
          postulent.
        </p>
      </div>

      {needsSubscription ? (
        <SubscriptionGate />
      ) : (
        <>
          <form
            onSubmit={applyFilters}
            className="mb-8 flex flex-col gap-3 rounded-lg border border-border bg-card p-4"
          >
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <div className="lg:col-span-2">
                <label htmlFor="cvtheque-q" className="sr-only">
                  Métier, compétence ou formation
                </label>
                <Input
                  id="cvtheque-q"
                  value={q}
                  onChange={(e) => setQ(e.target.value)}
                  placeholder="Métier, compétence, formation…"
                />
              </div>
              <div>
                <label htmlFor="cvtheque-city" className="sr-only">
                  Ville
                </label>
                <Input
                  id="cvtheque-city"
                  value={city}
                  onChange={(e) => setCity(e.target.value)}
                  placeholder="Ville"
                />
              </div>
              <div>
                <label htmlFor="cvtheque-language" className="sr-only">
                  Langue
                </label>
                <Input
                  id="cvtheque-language"
                  value={language}
                  onChange={(e) => setLanguage(e.target.value)}
                  placeholder="Langue (ex : anglais)"
                />
              </div>
            </div>
            <div className="flex flex-wrap items-center justify-between gap-3">
              <label className="flex cursor-pointer items-center gap-2 font-inter text-sm text-foreground">
                <input
                  type="checkbox"
                  checked={hasLicense}
                  onChange={(e) => setHasLicense(e.target.checked)}
                  className="h-4 w-4 rounded border-border accent-jeuncy-coral"
                />
                Titulaire du permis
              </label>
              <Button type="submit" variant="gradient" size="sm">
                <Search className="mr-1.5 h-4 w-4" aria-hidden="true" />
                Rechercher
              </Button>
            </div>
          </form>

          {query.isLoading && (
            <p className="font-inter text-muted-foreground">Chargement des profils…</p>
          )}

          {query.isError && !needsSubscription && (
            <p role="alert" className="font-inter text-destructive">
              Impossible de charger les profils pour le moment.
            </p>
          )}

          {query.data && (
            <>
              <p className="mb-4 font-inter text-sm text-muted-foreground">
                {query.data.total} profil{query.data.total > 1 ? 's' : ''} trouvé
                {query.data.total > 1 ? 's' : ''}
              </p>

              {query.data.data.length === 0 ? (
                <p className="rounded-lg border border-border bg-muted/30 p-8 text-center font-inter text-muted-foreground">
                  Aucun profil ne correspond à cette recherche. Essayez avec moins de
                  critères.
                </p>
              ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                  {query.data.data.map((candidate) => (
                    <CandidateCard key={candidate.id} candidate={candidate} />
                  ))}
                </div>
              )}

              {query.data.last_page > 1 && (
                <div className="mt-8 flex items-center justify-center gap-3">
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={query.data.current_page <= 1}
                    onClick={() => goToPage(query.data!.current_page - 1)}
                  >
                    Précédent
                  </Button>
                  <span className="font-inter text-sm text-muted-foreground">
                    Page {query.data.current_page} sur {query.data.last_page}
                  </span>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={query.data.current_page >= query.data.last_page}
                    onClick={() => goToPage(query.data!.current_page + 1)}
                  >
                    Suivant
                  </Button>
                </div>
              )}
            </>
          )}
        </>
      )}
    </main>
  );
}
