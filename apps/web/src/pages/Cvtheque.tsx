import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import {
  Search,
  Car,
  Cake,
  Lock,
  Languages as LanguagesIcon,
  Briefcase,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
  searchCvtheque,
  type CandidateCard as CandidateCardData,
  type CvthequeSearchFilters,
} from '@/lib/api/cvtheque';
import { ApiError } from '@/lib/api/client';
import { ageBandLabel } from '@/lib/age-band-labels';
import {
  candidateDisplayName,
  candidateInitials as initials,
} from '@/lib/candidate-card';
import { contractTypeLabel } from '@/lib/contract-type-labels';
import { offerSectorLabel } from '@/lib/offer-sector-labels';

// Etat de recherche porte par l'URL (comme JobOffers.tsx) : un recruteur peut
// mettre une recherche en favori ou la partager a un collegue.
//
// Plus de filtre « ville » : la ville n'est plus montree, et pouvoir filtrer
// dessus la revelerait par inference — taper « Perpignan » et compter les
// resultats vaut affichage (MOBILE.md §4.3).
function filtersFromParams(params: URLSearchParams): CvthequeSearchFilters {
  return {
    q: params.get('q') ?? undefined,
    language: params.get('language') ?? undefined,
    has_driving_license: params.get('has_driving_license') === '1' || undefined,
    age_min: Number(params.get('age_min')) || undefined,
    age_max: Number(params.get('age_max')) || undefined,
    skills: params.getAll('skills').filter(Boolean),
    page: Number(params.get('page') ?? '1') || 1,
  };
}

// Prenom + initiale : c'est tout ce que porte la carte. L'initiale vient du
// serveur (last_name_initial), le nom complet n'est jamais transmis.

// Ecran affiche si le serveur repond 402. Depuis que Jeuncy est gratuit
// pour les entreprises (2026-09-15) ce cas ne se produit plus — hasPaidAccess
// accorde la CVtheque a toute entreprise — mais la reponse existe toujours
// cote API, et un ecran qui vendrait un abonnement disparu serait pire
// qu'un message neutre. Aucun profil n'est montre, meme floute.
function SubscriptionGate() {
  return (
    <Card className="mx-auto max-w-2xl overflow-hidden border-2 border-primary/40">
      <div className="h-1 bg-jeuncy-gradient" />
      <CardContent className="flex flex-col items-center gap-4 p-8 text-center">
        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-jeuncy-gradient text-white">
          <Lock className="h-6 w-6" aria-hidden="true" />
        </div>
        <h2 className="font-poppins text-2xl font-bold text-foreground">
          La CVthèque n'est pas accessible depuis ce compte
        </h2>
        <p className="max-w-md font-inter text-sm text-muted-foreground">
          Elle est ouverte gratuitement à toutes les entreprises inscrites. Si tu vois ce
          message, écris-nous et nous regardons ton compte.
        </p>
        <Link to="/contact">
          <Button variant="gradient" size="lg">
            Nous écrire
          </Button>
        </Link>
      </CardContent>
    </Card>
  );
}

// Ecran affiche si le serveur repond 403 COMPANY_NOT_VERIFIED. Le message du
// serveur est repris tel quel : c'est lui qui sait pourquoi (SIRET manquant,
// registre en panne, activite refusee).
function VerificationGate({ message }: { message: string }) {
  return (
    <Card className="mx-auto max-w-2xl overflow-hidden border-2 border-primary/40">
      <div className="h-1 bg-jeuncy-gradient" />
      <CardContent className="flex flex-col items-center gap-4 p-8 text-center">
        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-jeuncy-gradient text-white">
          <Lock className="h-6 w-6" aria-hidden="true" />
        </div>
        <h2 className="font-poppins text-2xl font-bold text-foreground">
          Ton entreprise doit être vérifiée
        </h2>
        <p className="max-w-md font-inter text-sm text-muted-foreground">{message}</p>
        <Link to="/organization">
          <Button variant="gradient" size="lg">
            Compléter ma fiche entreprise
          </Button>
        </Link>
      </CardContent>
    </Card>
  );
}

function CandidateCard({ candidate }: { candidate: CandidateCardData }) {
  const ageLabel = ageBandLabel(candidate.age_band);

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
        {/* Photo seulement si le candidat l'a autorisee : le serveur renvoie
            null sinon, il n'y a donc rien a masquer cote client. */}
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
            {candidateDisplayName(candidate)}
          </p>
          {candidate.headline && (
            <p className="truncate font-inter text-sm text-muted-foreground">
              {candidate.headline}
            </p>
          )}
        </div>
      </div>

      {/* Aucune ville ici, et ce n'est pas un oubli : le lieu de residence
          n'est pas montre avant candidature. */}
      <div className="flex flex-wrap items-center gap-x-3 gap-y-1 font-inter text-xs text-muted-foreground">
        {ageLabel && (
          <span className="inline-flex items-center gap-1">
            <Cake className="h-3.5 w-3.5" aria-hidden="true" />
            {ageLabel}
          </span>
        )}
        {candidate.has_driving_license && (
          <span className="inline-flex items-center gap-1">
            <Car className="h-3.5 w-3.5" aria-hidden="true" />
            {candidate.driving_license_categories.length > 0
              ? `Permis ${candidate.driving_license_categories.join(', ')}`
              : 'Permis'}
            {candidate.has_vehicle ? ' · véhicule' : ''}
          </span>
        )}
        {candidate.languages.length > 0 && (
          <span className="inline-flex items-center gap-1">
            <LanguagesIcon className="h-3.5 w-3.5" aria-hidden="true" />
            {candidate.languages.map((l) => l.name).join(', ')}
          </span>
        )}
      </div>

      {(candidate.wanted_contract_types.length > 0 ||
        candidate.wanted_sectors.length > 0) && (
        <p className="inline-flex items-start gap-1 font-inter text-xs text-muted-foreground">
          <Briefcase className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
          <span>
            {[
              ...candidate.wanted_contract_types.map(contractTypeLabel),
              ...candidate.wanted_sectors.map(offerSectorLabel),
            ].join(' · ')}
          </span>
        </p>
      )}

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
  const [language, setLanguage] = useState(filters.language ?? '');
  const [hasLicense, setHasLicense] = useState(Boolean(filters.has_driving_license));
  const [ageMin, setAgeMin] = useState(filters.age_min ? String(filters.age_min) : '');
  const [ageMax, setAgeMax] = useState(filters.age_max ? String(filters.age_max) : '');

  const query = useQuery({
    queryKey: ['cvtheque', searchParams.toString()],
    queryFn: () => searchCvtheque(filters),
    // Un 402 (acces) et un 403 (verification) sont des reponses, pas des
    // pannes : inutile de reessayer.
    retry: (failureCount, error) =>
      !(error instanceof ApiError && [402, 403].includes(error.status)) &&
      failureCount < 3,
  });

  function applyFilters(event: React.FormEvent) {
    event.preventDefault();
    const next = new URLSearchParams();
    if (q.trim()) next.set('q', q.trim());
    if (language.trim()) next.set('language', language.trim());
    if (hasLicense) next.set('has_driving_license', '1');
    if (ageMin.trim()) next.set('age_min', ageMin.trim());
    if (ageMax.trim()) next.set('age_max', ageMax.trim());
    setSearchParams(next);
  }

  function goToPage(page: number) {
    const next = new URLSearchParams(searchParams);
    next.set('page', String(page));
    setSearchParams(next);
  }

  const apiError = query.error instanceof ApiError ? query.error : null;
  const needsSubscription = query.isError && apiError?.status === 402;
  const needsVerification = query.isError && apiError?.code === 'COMPANY_NOT_VERIFIED';
  const blocked = needsSubscription || needsVerification;

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
          postulent. Les coordonnées et le CV vous parviennent quand le candidat postule à
          l'une de vos offres.
        </p>
      </div>

      {needsSubscription && <SubscriptionGate />}
      {needsVerification && (
        <VerificationGate message={apiError?.message ?? 'Vérification en attente.'} />
      )}

      {!blocked && (
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
              {/* L'age : le cout d'un alternant depend de sa tranche d'age,
                  c'est un critere de selection a part entiere. Minimum 16, age
                  d'entree dans le modele match. */}
              <div className="flex items-center gap-2">
                <label htmlFor="cvtheque-age-min" className="sr-only">
                  Âge minimum
                </label>
                <Input
                  id="cvtheque-age-min"
                  type="number"
                  min={16}
                  max={99}
                  inputMode="numeric"
                  value={ageMin}
                  onChange={(e) => setAgeMin(e.target.value)}
                  placeholder="Âge min"
                />
                <span className="font-inter text-sm text-muted-foreground">à</span>
                <label htmlFor="cvtheque-age-max" className="sr-only">
                  Âge maximum
                </label>
                <Input
                  id="cvtheque-age-max"
                  type="number"
                  min={16}
                  max={99}
                  inputMode="numeric"
                  value={ageMax}
                  onChange={(e) => setAgeMax(e.target.value)}
                  placeholder="Âge max"
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

          {query.isError && (
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
