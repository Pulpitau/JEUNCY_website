import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Building2 } from 'lucide-react';
import { ContractType } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from '@/components/ui/card';
import { getPublicOffer } from '@/lib/api/job-offers';
import { ApiError } from '@/lib/api/client';
import { WORK_MODE_LABELS } from '@/lib/work-mode-labels';
import { ApplyToOfferSection } from '@/components/features/job-offers/ApplyToOfferSection';

const CONTRACT_TYPE_LABELS: Record<string, string> = {
  [ContractType.ALTERNANCE]: 'Alternance',
  [ContractType.SAISONNIER]: 'Saisonnier',
  [ContractType.BENEVOLAT]: 'Bénévolat',
  [ContractType.JOB_ETUDIANT]: 'Job étudiant',
  [ContractType.STAGE]: 'Stage',
};

export function JobOfferDetail() {
  const { id } = useParams<{ id: string }>();
  const offerId = Number(id);

  const offerQuery = useQuery({
    queryKey: ['job-offers', 'public', offerId],
    queryFn: () => getPublicOffer(offerId),
    retry: false,
    enabled: Number.isFinite(offerId),
  });

  if (offerQuery.isLoading) {
    return (
      <main className="mx-auto max-w-3xl px-4 py-12">
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      </main>
    );
  }

  if (offerQuery.isError) {
    const message =
      offerQuery.error instanceof ApiError
        ? offerQuery.error.message
        : 'Cette offre est introuvable.';

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

  const offer = offerQuery.data!;
  const publisher = offer.company ?? offer.cfa_organization;
  const isCfaOffer = offer.cfa_organization_id !== null;
  const skillsLabel = isCfaOffer
    ? 'Compétences et expériences acquises'
    : 'Compétences recherchées';

  return (
    <main className="mx-auto max-w-3xl px-4 py-12">
      <Link to="/offres" className="text-sm text-primary hover:underline">
        ← Retour aux offres
      </Link>

      <Card className="mt-4">
        <CardHeader>
          <Badge variant="outline" className="w-fit">
            {CONTRACT_TYPE_LABELS[offer.contract_type]}
          </Badge>
          <CardTitle className="text-2xl">{offer.title}</CardTitle>
          <div className="flex items-center gap-2">
            {publisher?.logo_url ? (
              <img
                src={publisher.logo_url}
                alt=""
                className="h-8 w-8 shrink-0 rounded border border-border object-contain"
              />
            ) : (
              <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded border border-border bg-muted">
                <Building2 className="h-4 w-4 text-muted-foreground" aria-hidden="true" />
              </div>
            )}
            <CardDescription>
              {publisher?.name}
              {offer.city ? ` · ${offer.city}` : ''}
            </CardDescription>
          </div>
        </CardHeader>
        <CardContent>
          <dl className="mb-4 flex flex-col gap-1 font-inter text-sm font-medium text-foreground">
            {offer.work_mode && (
              <div>
                <dt className="inline text-muted-foreground">Type d'offre : </dt>
                <dd className="inline">{WORK_MODE_LABELS[offer.work_mode]}</dd>
              </div>
            )}
            {offer.compensation && (
              <div>
                <dt className="inline text-muted-foreground">Rémunération : </dt>
                <dd className="inline">{offer.compensation}</dd>
              </div>
            )}
            {!isCfaOffer && offer.experience_level && (
              <div>
                <dt className="inline text-muted-foreground">Expérience requise : </dt>
                <dd className="inline">{offer.experience_level}</dd>
              </div>
            )}
            {isCfaOffer && offer.diploma_level && (
              <div>
                <dt className="inline text-muted-foreground">Niveau visé : </dt>
                <dd className="inline">{offer.diploma_level}</dd>
              </div>
            )}
            {isCfaOffer && offer.training_rhythm && (
              <div>
                <dt className="inline text-muted-foreground">
                  Rythme de l'alternance :{' '}
                </dt>
                <dd className="inline">{offer.training_rhythm}</dd>
              </div>
            )}
          </dl>

          <p className="whitespace-pre-line font-inter text-sm leading-relaxed text-foreground">
            {offer.description}
          </p>

          {offer.skills.length > 0 && (
            <div className="mt-4">
              <p className="mb-2 font-poppins text-sm font-medium text-foreground">
                {skillsLabel}
              </p>
              <div className="flex flex-wrap gap-1">
                {offer.skills.map((skill) => (
                  <Badge key={skill.id} variant="secondary">
                    {skill.name}
                  </Badge>
                ))}
              </div>
            </div>
          )}

          {!isCfaOffer && offer.benefits && (
            <div className="mt-4">
              <p className="mb-1 font-poppins text-sm font-medium text-foreground">
                Avantages
              </p>
              <p className="whitespace-pre-line font-inter text-sm text-muted-foreground">
                {offer.benefits}
              </p>
            </div>
          )}

          <ApplyToOfferSection jobOfferId={offer.id} />
        </CardContent>
      </Card>
    </main>
  );
}
