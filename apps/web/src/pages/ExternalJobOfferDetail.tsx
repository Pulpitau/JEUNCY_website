import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import {
  Building2,
  CalendarDays,
  Clock,
  ExternalLink,
  GraduationCap,
  MapPin,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { ApiError } from '@/lib/api/client';
import { WORK_MODE_LABELS } from '@/lib/work-mode-labels';
import { EXTERNAL_SOURCE_LABEL, getExternalOffer } from '@/lib/api/external-job-offers';
import { usePageMetadata } from '@/hooks/use-page-metadata';

// Fiche d'une offre importee de La bonne alternance. Pas de candidature
// Jeuncy : le candidat postule chez l'employeur, sur la page d'origine.
// C'est dit clairement avant qu'il clique — on ne fait pas croire a une
// candidature « en un clic » qui n'existe pas pour ces offres.
export function ExternalJobOfferDetail() {
  const { id } = useParams<{ id: string }>();
  const offerId = Number(id);

  const offerQuery = useQuery({
    queryKey: ['job-offers', 'external', offerId],
    queryFn: () => getExternalOffer(offerId),
    retry: false,
    enabled: Number.isFinite(offerId),
  });

  const offer = offerQuery.data;
  usePageMetadata(
    offer ? `${offer.title}${offer.city ? ` — ${offer.city}` : ''}` : 'Offre partenaire',
    offer ? offer.description.slice(0, 160) : undefined,
  );

  if (offerQuery.isLoading) {
    return (
      <main className="mx-auto max-w-3xl px-4 py-12">
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      </main>
    );
  }

  if (offerQuery.isError || !offer) {
    const message =
      offerQuery.error instanceof ApiError
        ? offerQuery.error.message
        : "Cette offre n'est plus disponible.";

    return (
      <main className="mx-auto max-w-3xl px-4 py-12">
        <p role="alert" className="font-inter text-sm text-destructive">
          {message}
        </p>
        <Link
          to="/offres"
          className="mt-4 inline-block text-sm text-primary hover:underline"
        >
          ← Retour aux offres
        </Link>
      </main>
    );
  }

  const contractStart = offer.contract_start
    ? new Date(offer.contract_start).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      })
    : null;

  return (
    <main className="mx-auto max-w-3xl px-4 py-12">
      <Link to="/offres" className="text-sm text-primary hover:underline">
        ← Retour aux offres
      </Link>

      <article className="mt-6 flex flex-col gap-6">
        <header className="flex flex-col gap-3">
          <div className="flex flex-wrap gap-1">
            <Badge variant="outline">Alternance</Badge>
            {offer.work_mode && (
              <Badge variant="outline">{WORK_MODE_LABELS[offer.work_mode]}</Badge>
            )}
            <Badge variant="secondary" className="gap-1">
              <ExternalLink className="h-3 w-3" aria-hidden="true" />
              via {EXTERNAL_SOURCE_LABEL}
            </Badge>
          </div>
          <h1 className="font-poppins text-3xl font-bold text-foreground">
            {offer.title}
          </h1>
          <div className="flex flex-wrap items-center gap-x-4 gap-y-1 font-inter text-sm text-muted-foreground">
            <span className="flex items-center gap-1">
              <Building2 className="h-4 w-4" aria-hidden="true" />
              {offer.company_name ?? 'Employeur non précisé'}
            </span>
            {(offer.city || offer.postal_code) && (
              <span className="flex items-center gap-1">
                <MapPin className="h-4 w-4" aria-hidden="true" />
                {[offer.postal_code, offer.city].filter(Boolean).join(' ')}
              </span>
            )}
            {offer.target_diploma_label && (
              <span className="flex items-center gap-1">
                <GraduationCap className="h-4 w-4" aria-hidden="true" />
                {offer.target_diploma_label}
              </span>
            )}
            {contractStart && (
              <span className="flex items-center gap-1">
                <CalendarDays className="h-4 w-4" aria-hidden="true" />
                Début {contractStart}
              </span>
            )}
            {offer.contract_duration_months && (
              <span className="flex items-center gap-1">
                <Clock className="h-4 w-4" aria-hidden="true" />
                {offer.contract_duration_months} mois
              </span>
            )}
          </div>
        </header>

        <section>
          <h2 className="font-poppins text-lg font-semibold text-foreground">Le poste</h2>
          <p className="mt-2 whitespace-pre-line font-inter text-sm leading-relaxed text-foreground">
            {offer.description}
          </p>
        </section>

        {(offer.company_naf_label || offer.company_size || offer.company_website) && (
          <section>
            <h2 className="font-poppins text-lg font-semibold text-foreground">
              L'employeur
            </h2>
            <dl className="mt-2 grid gap-1 font-inter text-sm text-muted-foreground sm:grid-cols-2">
              {offer.company_naf_label && (
                <div>
                  <dt className="font-medium text-foreground">Secteur</dt>
                  <dd>{offer.company_naf_label}</dd>
                </div>
              )}
              {offer.company_size && (
                <div>
                  <dt className="font-medium text-foreground">Effectif</dt>
                  <dd>{offer.company_size} salariés</dd>
                </div>
              )}
              {offer.company_website && (
                <div>
                  <dt className="font-medium text-foreground">Site web</dt>
                  <dd>
                    <a
                      href={offer.company_website}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-primary hover:underline"
                    >
                      {offer.company_website}
                    </a>
                  </dd>
                </div>
              )}
            </dl>
          </section>
        )}

        <Card className="overflow-hidden border-2 border-primary/30">
          <div className="h-1 bg-jeuncy-gradient" />
          <CardContent className="flex flex-col gap-3 p-6">
            <p className="font-poppins font-semibold text-foreground">
              Postuler à cette offre
            </p>
            <p className="font-inter text-sm text-muted-foreground">
              Cette offre est publiée sur {EXTERNAL_SOURCE_LABEL}, le service public de
              l'alternance. Tu postules directement auprès de l'employeur, sur leur site :
              pense à joindre le CV que tu as généré sur Jeuncy.
            </p>
            <a
              href={offer.apply_url}
              target="_blank"
              rel="noopener noreferrer"
              className="w-fit"
            >
              <Button variant="gradient" size="lg" className="gap-2">
                Postuler sur {EXTERNAL_SOURCE_LABEL}
                <ExternalLink className="h-4 w-4" aria-hidden="true" />
              </Button>
            </a>
          </CardContent>
        </Card>
      </article>
    </main>
  );
}
