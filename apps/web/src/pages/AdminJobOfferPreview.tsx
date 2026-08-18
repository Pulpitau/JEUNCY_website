import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Eye } from 'lucide-react';
import { JobOfferStatus } from '@jeuncy/shared';
import { previewJobOfferAsAdmin } from '@/lib/api/admin';
import { ApiError } from '@/lib/api/client';
import { PublicJobOfferView } from '@/components/features/job-offers/PublicJobOfferView';

const STATUS_LABELS: Record<string, string> = {
  [JobOfferStatus.DRAFT]: 'Brouillon — invisible pour les visiteurs',
  [JobOfferStatus.PUBLISHED]: 'Publiée — visible par tous',
  [JobOfferStatus.EXPIRED]: 'Expirée — retirée des recherches',
  [JobOfferStatus.ARCHIVED]: 'Archivée — retirée des recherches',
};

// Apercu back-office du rendu public d'une offre, quel que soit son statut.
// Meme composant de rendu que la page publique (PublicJobOfferView) : ce que
// l'admin voit ici est exactement ce que verra un visiteur, au bloc de
// candidature pres (retire — un admin ne postule pas). Voir
// AdminService::previewJobOffer cote serveur.
export function AdminJobOfferPreview() {
  const { id } = useParams<{ id: string }>();
  const offerId = Number(id);

  const offerQuery = useQuery({
    queryKey: ['admin', 'job-offers', 'preview', offerId],
    queryFn: () => previewJobOfferAsAdmin(offerId),
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
          to="/admin"
          className="mt-4 inline-block text-sm text-primary hover:underline"
        >
          ← Retour à l'administration
        </Link>
      </main>
    );
  }

  const offer = offerQuery.data!;

  return (
    <main className="mx-auto max-w-3xl px-4 py-12">
      <Link to="/admin" className="text-sm text-primary hover:underline">
        ← Retour à l'administration
      </Link>

      {/* Bandeau d'avertissement : cette page ressemble volontairement a la
          page publique, le bandeau est ce qui empeche de les confondre. */}
      <div className="mt-4 flex items-start gap-2 rounded-md border border-jeuncy-orange/40 bg-jeuncy-orange/10 p-3">
        <Eye className="mt-0.5 h-4 w-4 shrink-0 text-jeuncy-orange" aria-hidden="true" />
        <p className="font-inter text-sm text-foreground">
          <span className="font-semibold">Aperçu administrateur.</span> Rendu public exact
          de cette offre — statut actuel : {STATUS_LABELS[offer.status] ?? offer.status}.
        </p>
      </div>

      <PublicJobOfferView offer={offer} />
    </main>
  );
}
