import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { JobOfferForm } from '@/components/features/job-offers/JobOfferForm';
import { JobOfferListItem } from '@/components/features/job-offers/JobOfferListItem';
import {
  listMyOffers,
  createOffer,
  updateOffer,
  archiveOffer,
  createCheckoutSession,
} from '@/lib/api/job-offers';
import { ApiError } from '@/lib/api/client';

const OFFERS_QUERY_KEY = ['job-offers', 'mine'];

export function MyJobOffers() {
  const queryClient = useQueryClient();
  const [searchParams] = useSearchParams();
  const [showCreateForm, setShowCreateForm] = useState(false);
  const [checkoutError, setCheckoutError] = useState<string | null>(null);
  const checkoutStatus = searchParams.get('checkout');

  const offersQuery = useQuery({ queryKey: OFFERS_QUERY_KEY, queryFn: listMyOffers });

  function invalidateOffers() {
    return queryClient.invalidateQueries({ queryKey: OFFERS_QUERY_KEY });
  }

  const createMutation = useMutation({
    mutationFn: createOffer,
    onSuccess: invalidateOffers,
  });
  const updateMutation = useMutation({
    mutationFn: ({
      id,
      values,
    }: {
      id: number;
      values: Parameters<typeof updateOffer>[1];
    }) => updateOffer(id, values),
    onSuccess: invalidateOffers,
  });
  const archiveMutation = useMutation({
    mutationFn: archiveOffer,
    onSuccess: invalidateOffers,
  });
  const checkoutMutation = useMutation({
    mutationFn: createCheckoutSession,
    onSuccess: ({ checkout_url }) => {
      window.location.href = checkout_url;
    },
    onError: (error) => {
      setCheckoutError(
        error instanceof ApiError
          ? error.message
          : 'Impossible de démarrer le paiement pour le moment.',
      );
    },
  });

  const offers = offersQuery.data ?? [];

  return (
    <main className="mx-auto flex max-w-3xl flex-col gap-6 px-4 py-12">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h1 className="font-poppins text-3xl font-bold">Mes offres</h1>
          <p className="mt-1 font-inter text-muted-foreground">
            Crée, modifie et publie tes offres d'alternance, saisonnières ou bénévoles.
          </p>
        </div>
        {!showCreateForm && (
          <Button variant="gradient" onClick={() => setShowCreateForm(true)}>
            + Nouvelle offre
          </Button>
        )}
      </div>

      {checkoutStatus === 'success' && (
        <p className="rounded-md border border-jeuncy-orange bg-jeuncy-orange/10 px-4 py-3 font-inter text-sm text-foreground">
          Paiement en cours de validation — ton offre sera publiée dans quelques instants.
        </p>
      )}
      {checkoutStatus === 'cancelled' && (
        <p className="rounded-md border border-border px-4 py-3 font-inter text-sm text-muted-foreground">
          Paiement annulé, ton offre reste en brouillon.
        </p>
      )}
      {checkoutError && (
        <p role="alert" className="font-inter text-sm text-destructive">
          {checkoutError}
        </p>
      )}

      {showCreateForm && (
        <Card>
          <CardHeader>
            <CardTitle>Nouvelle offre</CardTitle>
          </CardHeader>
          <CardContent>
            <JobOfferForm
              isSubmitting={createMutation.isPending}
              onCancel={() => setShowCreateForm(false)}
              onSubmit={async (values) => {
                await createMutation.mutateAsync(values);
                setShowCreateForm(false);
              }}
            />
          </CardContent>
        </Card>
      )}

      {offersQuery.isLoading ? (
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      ) : offersQuery.isError ? (
        <p role="alert" className="font-inter text-sm text-destructive">
          Impossible de charger tes offres pour le moment, réessaie plus tard.
        </p>
      ) : offers.length === 0 ? (
        <p className="font-inter text-sm text-muted-foreground">
          Aucune offre pour l'instant. Crée ta première offre pour commencer.
        </p>
      ) : (
        <div className="flex flex-col gap-4">
          {offers.map((offer) => (
            <JobOfferListItem
              key={offer.id}
              offer={offer}
              isSubmitting={updateMutation.isPending || archiveMutation.isPending}
              isPublishing={checkoutMutation.isPending}
              onUpdate={(id, values) => updateMutation.mutateAsync({ id, values })}
              onArchive={(id) => archiveMutation.mutateAsync(id)}
              onPublish={(id) => {
                setCheckoutError(null);
                return checkoutMutation.mutateAsync(id);
              }}
            />
          ))}
        </div>
      )}
    </main>
  );
}
