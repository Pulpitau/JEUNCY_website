import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { UserRole } from '@jeuncy/shared';
import { Sparkles } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { JobOfferForm } from '@/components/features/job-offers/JobOfferForm';
import { JobOfferListItem } from '@/components/features/job-offers/JobOfferListItem';
import {
  listMyOffers,
  createOffer,
  updateOffer,
  archiveOffer,
  deleteOffer,
  publishOfferForFree,
} from '@/lib/api/job-offers';
import { ApiError } from '@/lib/api/client';
import { useAuthStore } from '@/store/auth-store';

const OFFERS_QUERY_KEY = ['job-offers', 'mine'];

// Jeuncy est gratuit pour les entreprises depuis le 2026-09-15 : ce tableau
// de bord a perdu l'essai, l'abonnement, l'offre d'ouverture et le passage
// par Stripe. Un seul geste reste : « Publier ». Le code de ces parcours
// existe toujours cote API (desactive), le front, lui, ne les montre plus —
// un bouton payant qu'on aurait « juste cache » finirait par reapparaitre.
export function MyJobOffers() {
  const queryClient = useQueryClient();
  const [showCreateForm, setShowCreateForm] = useState(false);
  const [publishError, setPublishError] = useState<string | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [createError, setCreateError] = useState<string | null>(null);
  const [createErrorCode, setCreateErrorCode] = useState<string | null>(null);
  const user = useAuthStore((state) => state.user);
  const isCfa = user?.role === UserRole.CFA;

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
  const deleteMutation = useMutation({
    mutationFn: deleteOffer,
    onSuccess: invalidateOffers,
    onError: (error) => {
      setDeleteError(
        error instanceof ApiError
          ? error.message
          : "Impossible de supprimer l'offre pour le moment.",
      );
    },
  });
  const publishMutation = useMutation({
    mutationFn: publishOfferForFree,
    onSuccess: invalidateOffers,
    onError: (error) => {
      setPublishError(
        error instanceof ApiError
          ? error.message
          : "Impossible de publier l'offre pour le moment.",
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

      {publishError && (
        <p role="alert" className="font-inter text-sm text-destructive">
          {publishError}
        </p>
      )}
      {deleteError && (
        <p role="alert" className="font-inter text-sm text-destructive">
          {deleteError}
        </p>
      )}

      {/* Rappel du modele, la ou l'entreprise agit : une entreprise qui a
          connu d'autres plateformes cherche le piege avant de publier. */}
      <div className="flex items-start gap-3 rounded-md border border-jeuncy-orange/40 bg-jeuncy-orange/10 px-4 py-3">
        <Sparkles
          className="mt-0.5 h-4 w-4 shrink-0 text-jeuncy-orange"
          aria-hidden="true"
        />
        <p className="font-inter text-sm text-foreground">
          <span className="font-poppins font-semibold">
            Publication gratuite et illimitée.
          </span>{' '}
          Tes offres restent en ligne tant que tu ne les archives pas, et toutes les
          candidatures te sont transmises avec le CV du candidat.
        </p>
      </div>

      {showCreateForm && (
        <Card>
          <CardHeader>
            <CardTitle>Nouvelle offre</CardTitle>
          </CardHeader>
          <CardContent>
            <JobOfferForm
              variant={isCfa ? 'CFA' : 'COMPANY'}
              isSubmitting={createMutation.isPending}
              submitError={createError}
              onCancel={() => setShowCreateForm(false)}
              onSubmit={async (values) => {
                setCreateError(null);
                setCreateErrorCode(null);
                try {
                  await createMutation.mutateAsync(values);
                  setShowCreateForm(false);
                } catch (error) {
                  setCreateError(
                    error instanceof ApiError
                      ? error.message
                      : "Impossible de créer l'offre pour le moment.",
                  );
                  setCreateErrorCode(error instanceof ApiError ? error.code : null);
                }
              }}
            />
          </CardContent>
          {(createErrorCode === 'COMPANY_NOT_FOUND' ||
            createErrorCode === 'CFA_ORGANIZATION_NOT_FOUND') && (
            <CardContent className="pt-0">
              <p className="font-inter text-sm text-muted-foreground">
                <Link to="/organization" className="text-primary hover:underline">
                  Complète d'abord ton profil entreprise
                </Link>{' '}
                avant de pouvoir créer une offre.
              </p>
            </CardContent>
          )}
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
              onUpdate={(id, values) => updateMutation.mutateAsync({ id, values })}
              onArchive={(id) => archiveMutation.mutateAsync(id)}
              isDeleting={deleteMutation.isPending}
              onDelete={(id) => {
                setDeleteError(null);
                return deleteMutation.mutateAsync(id);
              }}
              isPublishing={publishMutation.isPending}
              onPublish={(id) => {
                setPublishError(null);
                return publishMutation.mutateAsync(id);
              }}
            />
          ))}
        </div>
      )}
    </main>
  );
}
