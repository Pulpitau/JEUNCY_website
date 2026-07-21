import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
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
import { ApplyToOfferSection } from '@/components/features/job-offers/ApplyToOfferSection';

const CONTRACT_TYPE_LABELS: Record<string, string> = {
  [ContractType.ALTERNANCE]: 'Alternance',
  [ContractType.SAISONNIER]: 'Saisonnier',
  [ContractType.BENEVOLAT]: 'Bénévolat',
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
          <CardDescription>
            {publisher?.name}
            {offer.city ? ` · ${offer.city}` : ''}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <p className="whitespace-pre-line font-inter text-sm leading-relaxed text-foreground">
            {offer.description}
          </p>

          <ApplyToOfferSection jobOfferId={offer.id} />
        </CardContent>
      </Card>
    </main>
  );
}
