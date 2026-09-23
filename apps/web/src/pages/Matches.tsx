import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { ApplicationStatus, UserRole } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ageBandLabel } from '@/lib/age-band-labels';
import { ApiError } from '@/lib/api/client';
import { candidateDisplayName } from '@/lib/candidate-card';
import { contractTypeLabel } from '@/lib/contract-type-labels';
import {
  listMatches,
  type CandidateMatch,
  type EmployerMatch,
  type MatchApplication,
} from '@/lib/api/matches';
import { useAuthStore } from '@/store/auth-store';

// « Mes matchs », des deux côtés.
//
// Un match n'est pas une fin : c'est un rendez-vous manqué tant que personne
// ne bouge, et c'est TOUJOURS au candidat de bouger en premier — le match
// ouvre la conversation, il ne livre ni le CV ni les coordonnées (MOBILE.md
// §4.3). Chaque carte dit donc en toutes lettres à qui est la prochaine
// action, parce que « vous avez un match » sans suite est exactement ce qui
// a tué les produits de swipe de l'emploi.
//
// Une seule route pour les deux rôles : c'est le serveur qui choisit la forme
// de la réponse (MatchService::presenterMatch).

export const MATCHES_QUERY_KEY = ['matches'];

const STATUS_LABELS: Record<string, string> = {
  [ApplicationStatus.SENT]: 'Envoyée',
  [ApplicationStatus.SEEN]: 'Vue',
  [ApplicationStatus.INTERVIEW]: 'Entretien',
  [ApplicationStatus.ACCEPTED]: 'Acceptée',
  [ApplicationStatus.REJECTED]: 'Refusée',
};

function statusVariant(
  status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
  if (status === ApplicationStatus.ACCEPTED) return 'default';
  if (status === ApplicationStatus.REJECTED) return 'destructive';
  if (status === ApplicationStatus.INTERVIEW) return 'secondary';
  return 'outline';
}

function formatDate(value: string): string {
  return new Date(value).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
  });
}

export function Matches() {
  const role = useAuthStore((state) => state.user?.role);
  const isEmployer = role === UserRole.COMPANY || role === UserRole.CFA;

  return (
    <main className="mx-auto flex max-w-3xl flex-col gap-6 px-4 py-12">
      <div>
        <h1 className="font-poppins text-3xl font-bold">Mes matchs</h1>
        <p className="mt-1 font-inter text-muted-foreground">
          {isEmployer
            ? 'Ces candidats ont répondu à ton intérêt.'
            : 'Ces recruteurs veulent te lire. À toi d’envoyer ton dossier.'}
        </p>
      </div>

      {isEmployer ? <EmployerMatches /> : <CandidateMatches />}
    </main>
  );
}

// ---------------------------------------------------------------------------
// Candidat
// ---------------------------------------------------------------------------

function CandidateMatches() {
  const query = useQuery({
    queryKey: MATCHES_QUERY_KEY,
    queryFn: () => listMatches<CandidateMatch>(),
    retry: false,
  });

  if (query.isPending) return <Loading />;
  if (query.error) return <LoadError error={query.error} />;

  const matches = query.data ?? [];

  if (matches.length === 0) {
    return (
      <EmptyState
        title="Pas encore de match"
        description="Un match arrive quand un recruteur et toi dites oui tous les deux. Continue à parcourir les offres."
        action={{ to: '/offres', label: 'Voir les offres' }}
      />
    );
  }

  return (
    <div className="flex flex-col gap-4">
      {matches.map((match) => {
        const publisher = match.job_offer.company ?? match.job_offer.cfa_organization;
        const dossier = match.application;

        return (
          <Card key={match.id}>
            <CardHeader className="flex flex-row items-start justify-between gap-3">
              <div className="min-w-0">
                <CardTitle className="font-poppins text-lg">
                  <Link
                    to={`/offres/${match.job_offer.id}`}
                    className="hover:text-primary"
                  >
                    {match.job_offer.title}
                  </Link>
                </CardTitle>
                <p className="mt-1 font-inter text-sm text-muted-foreground">
                  {publisher?.name ?? 'Recruteur'} · match du{' '}
                  {formatDate(match.matched_at)}
                </p>
              </div>
              <Badge variant={dossier ? statusVariant(dossier.status) : 'secondary'}>
                {dossier ? STATUS_LABELS[dossier.status] : 'Dossier à envoyer'}
              </Badge>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
              {dossier === null ? (
                <>
                  <p className="font-inter text-sm">
                    Envoie ton dossier pour que le recruteur puisse te répondre. Sans lui,
                    il ne voit que ta carte — ni ton nom complet, ni tes coordonnées, ni
                    ton CV.
                  </p>
                  <Link to={`/offres/${match.job_offer.id}`} className="self-start">
                    <Button variant="gradient">Envoyer mon dossier</Button>
                  </Link>
                </>
              ) : (
                <Link to="/mes-candidatures" className="self-start">
                  <Button variant="outline">Suivre ma candidature</Button>
                </Link>
              )}
            </CardContent>
          </Card>
        );
      })}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Entreprise et CFA
// ---------------------------------------------------------------------------

function EmployerMatches() {
  const query = useQuery({
    queryKey: MATCHES_QUERY_KEY,
    queryFn: () => listMatches<EmployerMatch>(),
    // Pas de nouvelle tentative : les seuls echecs attendus ici sont
    // definitifs (403 entreprise non verifiee, 401 session expiree). Avec la
    // politique par defaut, une entreprise non verifiee attendait neuf
    // secondes et quatre requetes devant « Chargement des matchs… » avant de
    // lire ce qui lui manquait — mesure faite au navigateur le 2026-09-23.
    retry: false,
  });

  if (query.isPending) return <Loading />;
  if (query.error) return <LoadError error={query.error} />;

  const matches = query.data ?? [];

  if (matches.length === 0) {
    return (
      <EmptyState
        title="Pas encore de match"
        description="Un match arrive quand un candidat et toi dites oui tous les deux. Les profils se parcourent depuis l’application Jeuncy."
        action={{ to: '/candidats', label: 'Ouvrir la CVthèque' }}
      />
    );
  }

  return (
    <div className="flex flex-col gap-4">
      {matches.map((match) => {
        const candidate = match.candidate;
        const dossier = match.application;

        return (
          <Card key={match.id}>
            <CardHeader className="flex flex-row items-start justify-between gap-3">
              <div className="min-w-0">
                <CardTitle className="font-poppins text-lg">
                  {candidate ? candidateDisplayName(candidate) : 'Candidat'}
                </CardTitle>
                <p className="mt-1 font-inter text-sm text-muted-foreground">
                  {match.job_offer?.title ?? 'Offre supprimée'} · match du{' '}
                  {formatDate(match.matched_at)}
                </p>
              </div>
              <Badge variant={dossier ? statusVariant(dossier.status) : 'secondary'}>
                {dossier ? STATUS_LABELS[dossier.status] : 'Attend son dossier'}
              </Badge>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
              {candidate && (
                <div className="flex flex-col gap-1 font-inter text-sm">
                  {candidate.headline && (
                    <p className="font-semibold">{candidate.headline}</p>
                  )}
                  <p className="text-muted-foreground">
                    {[
                      ageBandLabel(candidate.age_band),
                      ...candidate.wanted_contract_types.map(contractTypeLabel),
                    ]
                      .filter(Boolean)
                      .join(' · ')}
                  </p>
                </div>
              )}

              {dossier ? (
                <Dossier application={dossier} />
              ) : (
                // Ce que l'employeur ne voit pas encore est dit explicitement :
                // sans cela, il attend un écran qui ne viendra pas, ou croit à
                // un profil incomplet.
                <p className="rounded-md border border-border bg-muted/40 p-3 font-inter text-sm text-muted-foreground">
                  On l’a prévenu·e par notification et par email. Son nom complet, ses
                  coordonnées et son CV arrivent avec son dossier.
                </p>
              )}
            </CardContent>
          </Card>
        );
      })}
    </div>
  );
}

/** Le dossier reçu : le seul endroit où l'employeur voit les coordonnées. */
function Dossier({ application }: { application: MatchApplication }) {
  const profile = application.candidate_profile;
  const cvUrl = application.generated_cv?.file_url ?? application.cv_file_url;

  return (
    <div className="flex flex-col gap-2 rounded-md border border-border p-3 font-inter text-sm">
      <p className="font-poppins font-semibold">
        {profile.first_name} {profile.last_name}
      </p>
      <p className="text-muted-foreground">
        {[profile.user.email, application.contact_phone ?? profile.phone]
          .filter(Boolean)
          .join(' · ')}
      </p>
      {application.cover_letter && (
        <p className="whitespace-pre-line text-muted-foreground">
          {application.cover_letter}
        </p>
      )}
      <div className="flex flex-wrap gap-2 pt-1">
        {cvUrl && (
          <a href={cvUrl} target="_blank" rel="noreferrer">
            <Button variant="outline" size="sm">
              Ouvrir le CV
            </Button>
          </a>
        )}
        <Link to="/mes-offres">
          <Button variant="ghost" size="sm">
            Répondre depuis mes offres
          </Button>
        </Link>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------

function Loading() {
  return (
    <p className="font-inter text-sm text-muted-foreground">Chargement des matchs…</p>
  );
}

/**
 * Un échec de chargement, et surtout celui qui a une suite.
 *
 * « Ton entreprise doit être vérifiée » n'est pas une erreur, c'est une étape
 * manquante : sans un bouton qui mène à la fiche, l'employeur lit une phrase
 * vraie et reste devant une page morte. Même traitement que la CVthèque, qui
 * bute sur la même garde serveur.
 */
function LoadError({ error }: { error: unknown }) {
  const code = error instanceof ApiError ? error.code : null;

  if (code === 'COMPANY_NOT_VERIFIED') {
    return (
      <EmptyState
        title="Ton entreprise doit être vérifiée"
        description={
          error instanceof Error
            ? error.message
            : 'La vérification part automatiquement dès que ton SIRET est renseigné.'
        }
        action={{ to: '/organization', label: 'Compléter ma fiche' }}
      />
    );
  }

  return (
    <p role="alert" className="font-inter text-sm text-destructive">
      {error instanceof Error ? error.message : 'Chargement impossible.'}
    </p>
  );
}

function EmptyState({
  title,
  description,
  action,
}: {
  title: string;
  description: string;
  action: { to: string; label: string };
}) {
  return (
    <Card>
      <CardContent className="flex flex-col items-center gap-3 py-10 text-center">
        <h2 className="font-poppins text-xl font-bold">{title}</h2>
        <p className="max-w-md font-inter text-sm text-muted-foreground">{description}</p>
        <Link to={action.to}>
          <Button variant="outline">{action.label}</Button>
        </Link>
      </CardContent>
    </Card>
  );
}
