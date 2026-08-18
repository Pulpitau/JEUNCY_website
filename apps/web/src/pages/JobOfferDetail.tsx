import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getPublicOffer } from '@/lib/api/job-offers';
import { ApiError } from '@/lib/api/client';
import { PublicJobOfferView } from '@/components/features/job-offers/PublicJobOfferView';
import { ApplyToOfferSection } from '@/components/features/job-offers/ApplyToOfferSection';

// Page publique de detail d'une offre. Le rendu visuel vit dans
// PublicJobOfferView, partage avec l'apercu back-office
// (AdminJobOfferPreview) : cette page n'apporte que le fetch public et le
// bloc de candidature.
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

  return (
    <main className="mx-auto max-w-3xl px-4 py-12">
      <Link to="/offres" className="text-sm text-primary hover:underline">
        ← Retour aux offres
      </Link>

      <PublicJobOfferView
        offer={offer}
        footer={<ApplyToOfferSection jobOfferId={offer.id} />}
      />
    </main>
  );
}
