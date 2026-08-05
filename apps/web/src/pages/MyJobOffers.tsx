import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { UserRole, SubscriptionStatus } from '@jeuncy/shared';
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
  publishOfferViaTrial,
  publishOfferViaSubscription,
} from '@/lib/api/job-offers';
import { getMyCompany } from '@/lib/api/company';
import { getMyCfaOrganization } from '@/lib/api/cfa-organization';
import {
  COMPANY_OFFER_PRICE_LABEL,
  CFA_OFFER_PRICE_LABEL,
  APPLICATIONS_UNLOCK_PRICE_LABEL,
} from '@/lib/api/job-offers';
import {
  getMySubscription,
  createSubscriptionCheckoutSession,
  cancelSubscription,
  COMPANY_SUBSCRIPTION_PRICE_LABEL,
  CFA_SUBSCRIPTION_PRICE_LABEL,
} from '@/lib/api/subscriptions';
import { ApiError } from '@/lib/api/client';
import { useAuthStore } from '@/store/auth-store';

const OFFERS_QUERY_KEY = ['job-offers', 'mine'];
const COMPANY_QUERY_KEY = ['company'];
const CFA_QUERY_KEY = ['cfa-organization'];
const SUBSCRIPTION_QUERY_KEY = ['subscription', 'mine'];
const TRIAL_DURATION_DAYS = 15;
const TRIAL_MAX_OFFERS = 1;

export function MyJobOffers() {
  const queryClient = useQueryClient();
  const [searchParams] = useSearchParams();
  const [showCreateForm, setShowCreateForm] = useState(false);
  const [checkoutError, setCheckoutError] = useState<string | null>(null);
  const [trialError, setTrialError] = useState<string | null>(null);
  const [subscriptionPublishError, setSubscriptionPublishError] = useState<string | null>(
    null,
  );
  const [createError, setCreateError] = useState<string | null>(null);
  const [createErrorCode, setCreateErrorCode] = useState<string | null>(null);
  const [subscriptionError, setSubscriptionError] = useState<string | null>(null);
  const checkoutStatus = searchParams.get('checkout');
  const subscriptionStatus = searchParams.get('subscription');
  const user = useAuthStore((state) => state.user);
  const isCompany = user?.role === UserRole.COMPANY;
  const isCfa = user?.role === UserRole.CFA;
  const canUseTrial = isCompany || isCfa;

  const offersQuery = useQuery({ queryKey: OFFERS_QUERY_KEY, queryFn: listMyOffers });
  const companyQuery = useQuery({
    queryKey: COMPANY_QUERY_KEY,
    queryFn: getMyCompany,
    retry: false,
    enabled: isCompany,
  });
  const cfaQuery = useQuery({
    queryKey: CFA_QUERY_KEY,
    queryFn: getMyCfaOrganization,
    retry: false,
    enabled: isCfa,
  });
  const subscriptionQuery = useQuery({
    queryKey: SUBSCRIPTION_QUERY_KEY,
    queryFn: getMySubscription,
    enabled: canUseTrial,
  });
  const subscription = subscriptionQuery.data ?? null;
  const hasActiveSubscription = subscription?.status === SubscriptionStatus.ACTIVE;

  const subscriptionCheckoutMutation = useMutation({
    mutationFn: createSubscriptionCheckoutSession,
    onSuccess: ({ checkout_url }) => {
      window.location.href = checkout_url;
    },
    onError: (error) => {
      setSubscriptionError(
        error instanceof ApiError
          ? error.message
          : "Impossible de démarrer l'abonnement pour le moment.",
      );
    },
  });
  const cancelSubscriptionMutation = useMutation({
    mutationFn: cancelSubscription,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: SUBSCRIPTION_QUERY_KEY }),
    onError: (error) => {
      setSubscriptionError(
        error instanceof ApiError
          ? error.message
          : "Impossible d'annuler l'abonnement pour le moment.",
      );
    },
  });

  // Company et CfaOrganization portent les memes champs trial_started_at /
  // trial_offers_count (voir JobOfferService::trialHolder cote backend) : seul
  // l'un des deux est charge selon le role, jamais les deux.
  const organization = isCompany ? companyQuery.data : isCfa ? cfaQuery.data : undefined;
  const trialStartedAt = organization?.trial_started_at
    ? new Date(organization.trial_started_at)
    : null;
  const trialEndsAt = trialStartedAt
    ? new Date(trialStartedAt.getTime() + TRIAL_DURATION_DAYS * 24 * 60 * 60 * 1000)
    : null;
  const trialOffersUsed = organization?.trial_offers_count ?? 0;
  const trialOffersRemaining = Math.max(0, TRIAL_MAX_OFFERS - trialOffersUsed);
  const trialAvailable =
    canUseTrial &&
    !!organization &&
    trialOffersRemaining > 0 &&
    (!trialEndsAt || trialEndsAt.getTime() > Date.now());

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
  const trialMutation = useMutation({
    mutationFn: publishOfferViaTrial,
    onSuccess: () => {
      invalidateOffers();
      queryClient.invalidateQueries({
        queryKey: isCompany ? COMPANY_QUERY_KEY : CFA_QUERY_KEY,
      });
    },
    onError: (error) => {
      setTrialError(
        error instanceof ApiError
          ? error.message
          : "Impossible de publier l'offre gratuitement pour le moment.",
      );
    },
  });

  const subscriptionPublishMutation = useMutation({
    mutationFn: publishOfferViaSubscription,
    onSuccess: invalidateOffers,
    onError: (error) => {
      setSubscriptionPublishError(
        error instanceof ApiError
          ? error.message
          : "Impossible de publier l'offre via l'abonnement pour le moment.",
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
      {checkoutStatus === 'applications_success' && (
        <p className="rounded-md border border-jeuncy-orange bg-jeuncy-orange/10 px-4 py-3 font-inter text-sm text-foreground">
          Paiement en cours de validation — l'accès aux candidatures de cette offre sera
          débloqué dans quelques instants.
        </p>
      )}
      {checkoutStatus === 'applications_cancelled' && (
        <p className="rounded-md border border-border px-4 py-3 font-inter text-sm text-muted-foreground">
          Paiement annulé, les candidatures restent verrouillées.
        </p>
      )}
      {subscriptionStatus === 'success' && (
        <p className="rounded-md border border-jeuncy-orange bg-jeuncy-orange/10 px-4 py-3 font-inter text-sm text-foreground">
          Abonnement en cours de validation — il sera actif dans quelques instants.
        </p>
      )}
      {subscriptionStatus === 'cancelled' && (
        <p className="rounded-md border border-border px-4 py-3 font-inter text-sm text-muted-foreground">
          Souscription annulée.
        </p>
      )}
      {checkoutError && (
        <p role="alert" className="font-inter text-sm text-destructive">
          {checkoutError}
        </p>
      )}
      {trialError && (
        <p role="alert" className="font-inter text-sm text-destructive">
          {trialError}
        </p>
      )}
      {subscriptionError && (
        <p role="alert" className="font-inter text-sm text-destructive">
          {subscriptionError}
        </p>
      )}
      {subscriptionPublishError && (
        <p role="alert" className="font-inter text-sm text-destructive">
          {subscriptionPublishError}
        </p>
      )}

      {canUseTrial && organization && !hasActiveSubscription && (
        <p className="rounded-md border border-jeuncy-orange bg-jeuncy-orange/10 px-4 py-3 font-inter text-sm text-foreground">
          {!trialStartedAt ? (
            <>
              Essai gratuit disponible : publie 1 offre gratuitement pendant{' '}
              {TRIAL_DURATION_DAYS} jours, avec accès aux candidatures inclus.
            </>
          ) : trialAvailable ? (
            <>
              Essai gratuit en cours : encore {trialOffersRemaining} offre
              {trialOffersRemaining > 1 ? 's' : ''} gratuite
              {trialOffersRemaining > 1 ? 's' : ''}, jusqu'au{' '}
              {trialEndsAt?.toLocaleDateString('fr-FR')}.
            </>
          ) : (
            <>
              Ta période d'essai gratuite est terminée. Publier une nouvelle offre coûte{' '}
              {isCfa ? CFA_OFFER_PRICE_LABEL : COMPANY_OFFER_PRICE_LABEL}, et l'accès aux
              candidatures de cette offre {APPLICATIONS_UNLOCK_PRICE_LABEL} en plus — ou
              passe à l'abonnement mensuel pour tout inclure.
            </>
          )}
        </p>
      )}

      {canUseTrial && (
        <Card>
          <CardHeader>
            <CardTitle className="text-lg">Abonnement mensuel</CardTitle>
          </CardHeader>
          <CardContent>
            {hasActiveSubscription ? (
              <div className="flex flex-col gap-2">
                <p className="font-inter text-sm text-foreground">
                  Abonnement actif — publication illimitée et accès aux candidatures de
                  toutes tes offres.
                  {subscription?.canceled_at && (
                    <>
                      {' '}
                      Résiliation prévue
                      {subscription.current_period_end
                        ? ` le ${new Date(subscription.current_period_end).toLocaleDateString('fr-FR')}`
                        : ''}
                      .
                    </>
                  )}
                </p>
                {!subscription?.canceled_at && (
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="w-fit"
                    onClick={() => cancelSubscriptionMutation.mutate()}
                    disabled={cancelSubscriptionMutation.isPending}
                  >
                    {cancelSubscriptionMutation.isPending
                      ? 'Annulation…'
                      : "Résilier l'abonnement"}
                  </Button>
                )}
              </div>
            ) : (
              <div className="flex flex-col gap-2">
                <p className="font-inter text-sm text-muted-foreground">
                  Publication illimitée + accès aux candidatures de toutes tes offres,
                  pour{' '}
                  {isCfa
                    ? CFA_SUBSCRIPTION_PRICE_LABEL
                    : COMPANY_SUBSCRIPTION_PRICE_LABEL}
                  /mois. Résiliable à tout moment.
                </p>
                <Button
                  type="button"
                  variant="gradient"
                  size="sm"
                  className="w-fit"
                  onClick={() => {
                    setSubscriptionError(null);
                    subscriptionCheckoutMutation.mutate();
                  }}
                  disabled={subscriptionCheckoutMutation.isPending}
                >
                  {subscriptionCheckoutMutation.isPending ? 'Redirection…' : "S'abonner"}
                </Button>
              </div>
            )}
          </CardContent>
        </Card>
      )}

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
              isPublishing={checkoutMutation.isPending}
              onUpdate={(id, values) => updateMutation.mutateAsync({ id, values })}
              onArchive={(id) => archiveMutation.mutateAsync(id)}
              onPublish={(id) => {
                setCheckoutError(null);
                return checkoutMutation.mutateAsync(id);
              }}
              canUseTrial={canUseTrial}
              trialAvailable={trialAvailable}
              trialOffersRemaining={trialOffersRemaining}
              isPublishingTrial={trialMutation.isPending}
              onPublishTrial={(id) => {
                setTrialError(null);
                return trialMutation.mutateAsync(id);
              }}
              hasActiveSubscription={hasActiveSubscription}
              isPublishingViaSubscription={subscriptionPublishMutation.isPending}
              onPublishViaSubscription={(id) => {
                setSubscriptionPublishError(null);
                return subscriptionPublishMutation.mutateAsync(id);
              }}
            />
          ))}
        </div>
      )}
    </main>
  );
}
