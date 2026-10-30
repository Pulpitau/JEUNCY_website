import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Stack, useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { MatchSheet } from '@/components/features/discover/match-sheet';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Screen } from '@/components/ui/screen';
import { Text } from '@/components/ui/text';
import { DISCOVER_KEY } from '@/hooks/use-discover-deck';
import { useInterestedOffers } from '@/hooks/use-interested-offers';
import { MATCHES_KEY } from '@/hooks/use-matches';
import { ApiError } from '@/lib/api/client';
import type { DeckJeuncyOffer } from '@/lib/api/discover';
import { INTEREST_ERRORS, likeOffer, passOffers } from '@/lib/api/interests';
import { publisherOf } from '@/lib/api/job-offers';
import { formatCompensation } from '@/lib/format-compensation';
import { CONTRACT_TYPE_LABELS } from '@/lib/labels';
import { useTheme } from '@/theme/theme-provider';
import { spacing } from '@/theme/typography';

// « Ils s'intéressent à toi » : là où mène la notification INTEREST_RECEIVED.
// Miroir de la page web /interesses (InterestedInMe.tsx), même API : les
// offres dont l'employeur a dit oui en premier, auxquelles le candidat répond
// « Ça m'intéresse » (match immédiat) ou « Passer ».

type Action = { offerId: number; kind: 'like' | 'pass' };

export default function InteressesScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const queryClient = useQueryClient();
  const query = useInterestedOffers();

  // Offres traitées pendant cette visite : retirées sans attendre un
  // rechargement.
  const [handled, setHandled] = useState<number[]>([]);
  const [match, setMatch] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  const terminer = (offerId: number) => {
    setHandled((current) => [...current, offerId]);
    setActionError(null);
    // La pile Découvrir contient aussi ces offres : elle doit les oublier.
    void queryClient.invalidateQueries({ queryKey: DISCOVER_KEY });
  };

  const action = useMutation({
    mutationFn: async ({ offerId, kind }: Action) =>
      kind === 'like' ? likeOffer(offerId) : passOffers([offerId]),
    onSuccess: (result, { offerId, kind }) => {
      terminer(offerId);
      if (kind === 'like' && 'matched' in result && result.matched) {
        void queryClient.invalidateQueries({ queryKey: MATCHES_KEY });
        const offer = query.data?.find((o) => o.id === offerId);
        setMatch((offer && publisherOf(offer)?.name) ?? 'Cette entreprise');
      }
    },
    onError: (error) => setActionError(messageFor(error)),
  });

  const offers = (query.data ?? []).filter((offer) => !handled.includes(offer.id));

  return (
    <>
      <Stack.Screen options={{ title: "Ils s'intéressent à toi" }} />
      <Screen hasHeader>
        <View style={styles.page}>
          <Text variant="body" tone="muted">
            Ces recruteurs ont vu ton profil et aimeraient en savoir plus. À toi de dire
            si ça t&apos;intéresse.
          </Text>

          {actionError ? (
            <Text variant="small" tone="danger" accessibilityLiveRegion="polite">
              {actionError}
            </Text>
          ) : null}

          {query.isPending ? (
            <ActivityIndicator color={colors.accent} style={styles.spinner} />
          ) : query.isError ? (
            <Blocked error={query.error} onRetry={() => void query.refetch()} />
          ) : offers.length === 0 ? (
            <EmptyState
              title="Personne pour l'instant"
              description="Un profil complet est vu bien plus souvent : ajoute tes expériences, tes compétences et une phrase de présentation."
              action={{
                label: 'Compléter mon profil',
                onPress: () => router.push('/profil'),
              }}
            />
          ) : (
            offers.map((offer) => (
              <OfferRow
                key={offer.id}
                offer={offer}
                busy={action.isPending}
                pending={action.isPending ? (action.variables ?? null) : null}
                onLike={() => action.mutate({ offerId: offer.id, kind: 'like' })}
                onPass={() => action.mutate({ offerId: offer.id, kind: 'pass' })}
                onOpen={() =>
                  router.push({
                    pathname: '/offres/[id]',
                    params: { id: String(offer.id) },
                  })
                }
              />
            ))
          )}
        </View>
      </Screen>

      {match ? (
        <MatchSheet
          side="candidate"
          counterpartLabel={match}
          applicationSent={false}
          onSeeMatches={() => {
            setMatch(null);
            router.push('/matchs');
          }}
          onContinue={() => setMatch(null)}
        />
      ) : null}
    </>
  );
}

function OfferRow({
  offer,
  busy,
  pending,
  onLike,
  onPass,
  onOpen,
}: {
  offer: DeckJeuncyOffer;
  busy: boolean;
  pending: Action | null;
  onLike: () => void;
  onPass: () => void;
  onOpen: () => void;
}) {
  const publisher = publisherOf(offer);
  const remuneration = formatCompensation(
    offer.compensation_amount,
    offer.compensation_period,
    offer.compensation,
  );
  // La distance n'apparaît QUE de ce côté-ci : le candidat a le droit de
  // savoir où est le poste, l'employeur n'a pas le droit de savoir où habite
  // le candidat (L1132-1).
  const distance =
    offer.distance_km === null
      ? null
      : offer.distance_km < 1
        ? "à moins d'1 km"
        : `à ${Math.round(offer.distance_km)} km`;
  const ici = pending?.offerId === offer.id;

  return (
    <Card>
      <View style={styles.badges}>
        <Badge label="Intéressé par ton profil" tone="accent" />
        <Badge label={CONTRACT_TYPE_LABELS[offer.contract_type]} />
      </View>
      <Text variant="sectionTitle">{offer.title}</Text>
      <Text variant="small" tone="muted">
        {[publisher?.name, offer.city, distance, remuneration]
          .filter(Boolean)
          .join(' · ')}
      </Text>
      <Text variant="body" tone="muted" numberOfLines={3}>
        {offer.description}
      </Text>
      <View style={styles.actions}>
        {/* Le oui qui crée un match envoie notifications et emails DANS la
            requête : une dizaine de secondes, d'où l'indicateur d'attente. */}
        <Button
          label="Ça m'intéresse"
          disabled={busy}
          loading={ici && pending?.kind === 'like'}
          onPress={onLike}
        />
        <Button
          label="Passer"
          variant="secondary"
          disabled={busy}
          loading={ici && pending?.kind === 'pass'}
          onPress={onPass}
        />
        <Button label="Voir l'offre" variant="ghost" onPress={onOpen} />
      </View>
      <Text variant="small" tone="muted">
        Dire « ça m&apos;intéresse » ne transmet aucune coordonnée : c&apos;est toi qui
        décides ensuite d&apos;envoyer ton dossier.
      </Text>
    </Card>
  );
}

/** Les refus de la garde du match, chacun avec ce qu'il y a à faire. */
function Blocked({ error, onRetry }: { error: ApiError; onRetry: () => void }) {
  const router = useRouter();

  switch (error.code) {
    case 'CANDIDATE_PROFILE_REQUIRED':
    case 'PROFILE_NOT_FOUND':
      return (
        <EmptyState
          title="Crée ton profil"
          description="Les recruteurs ne peuvent s'intéresser qu'à un profil existant."
          action={{
            label: 'Créer mon profil',
            onPress: () => router.push('/profil/informations'),
          }}
        />
      );
    case 'BIRTH_DATE_REQUIRED':
      return (
        <EmptyState
          title="Il manque ta date de naissance"
          description="Elle sert à vérifier l'âge minimum. Les recruteurs ne voient jamais que ta tranche d'âge."
          action={{
            label: 'Compléter mon profil',
            onPress: () => router.push('/profil/informations'),
          }}
        />
      );
    case 'MATCH_MIN_AGE':
      return (
        <EmptyState
          title="Réservé aux 16 ans et plus"
          description="La mise en relation directe ouvre à 16 ans. En attendant, tu peux postuler normalement aux offres."
          action={{
            label: 'Voir les offres',
            onPress: () => router.push('/offres/recherche'),
          }}
        />
      );
    case 'PHOTO_REQUIRED':
      return (
        <EmptyState
          title="Ajoute ta photo de profil"
          description={error.message}
          action={{ label: 'Ajouter ma photo', onPress: () => router.push('/profil') }}
        />
      );
    default:
      return (
        <EmptyState
          title="Impossible de charger la liste"
          description={error.message}
          action={{ label: 'Réessayer', onPress: onRetry }}
        />
      );
  }
}

function messageFor(error: unknown): string {
  const code = error instanceof ApiError ? error.code : null;

  if (code === INTEREST_ERRORS.QUOTA) {
    return "Tu as utilisé tes « ça m'intéresse » du jour. Reviens demain.";
  }
  if (code === INTEREST_ERRORS.OFFER_UNPUBLISHED || code === INTEREST_ERRORS.CLOSED) {
    return "Cette offre vient d'être retirée.";
  }

  return error instanceof Error ? error.message : 'Réessaie dans un instant.';
}

const styles = StyleSheet.create({
  page: { gap: spacing.lg },
  spinner: { paddingVertical: spacing.xxl },
  badges: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  actions: { gap: spacing.sm, marginTop: spacing.sm },
});
