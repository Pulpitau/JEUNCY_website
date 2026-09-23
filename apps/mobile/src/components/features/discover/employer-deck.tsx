import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, Alert, Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { CandidateCardFace } from '@/components/features/discover/candidate-card';
import { CandidateDetailSheet } from '@/components/features/discover/candidate-detail-sheet';
import { MatchSheet } from '@/components/features/discover/match-sheet';
import { OfferPickerSheet } from '@/components/features/discover/offer-picker-sheet';
import {
  SwipeDeck,
  type SwipeDirection,
} from '@/components/features/discover/swipe-deck';
import { EmptyState } from '@/components/ui/empty-state';
import { Text } from '@/components/ui/text';
import { useCandidateDeck } from '@/hooks/use-candidate-deck';
import { useDiscoverableOffers } from '@/hooks/use-my-offers';
import { ApiError } from '@/lib/api/client';
import type { DeckCandidateCard } from '@/lib/api/discover';
import { INTEREST_ERRORS } from '@/lib/api/interests';
import type { JobOffer } from '@/lib/api/job-offers';
import { useTheme } from '@/theme/theme-provider';
import { spacing } from '@/theme/typography';

// « Decouvrir » cote entreprise et CFA (MOBILE.md §4.1).
//
// La pile depend d'une OFFRE, pas du compte : c'est elle qui porte le
// contrat, la commune et le rayon de recrutement, donc l'eligibilite des
// candidats. Un employeur qui change d'offre change de pile — d'ou le
// selecteur en tete plutot qu'un reglage cache.
//
// Le deck n'affiche jamais de score ni de distance : le serveur ne renvoie
// que des candidats eligibles, et la carte dit seulement que les deux rayons
// se couvrent (decision du 2026-09-22, L1132-1).

export function EmployerDeck() {
  const router = useRouter();
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();

  const { discoverable, offers, isPending: offersPending } = useDiscoverableOffers();

  const [chosenId, setChosenId] = useState<number | null>(null);
  const [choosingOffer, setChoosingOffer] = useState(false);
  const [opened, setOpened] = useState<DeckCandidateCard | null>(null);
  const [match, setMatch] = useState<{ label: string; applicationSent: boolean } | null>(
    null,
  );

  // L'offre qui porte la pile : celle qu'on a choisie si elle est toujours
  // utilisable, sinon la premiere disponible.
  //
  // Derivee du rendu plutot que posee par un effet. Un effet qui appelle
  // setOfferId provoquerait un rendu en cascade a chaque arrivee de la liste
  // — le deck se monterait une fois a vide, puis une seconde fois avec
  // l'offre. Et une offre archivee entre-temps disparait de `discoverable`,
  // donc le repli se fait tout seul, sans code de rattrapage.
  const offer =
    discoverable.find((candidate) => candidate.id === chosenId) ??
    discoverable[0] ??
    null;
  const offerId = offer?.id ?? null;
  const { deck, status, error, canUndo, like, pass, undo, refresh } =
    useCandidateDeck(offerId);

  const handleSwipe = (card: DeckCandidateCard, direction: SwipeDirection) => {
    if (direction === 'left') {
      pass(card);

      return;
    }

    void like(card)
      .then(({ matched, applicationSent }) => {
        if (!matched) return;
        setMatch({ label: labelOf(card), applicationSent });
      })
      .catch((cause: unknown) => signalerEchecInteret(cause));
  };

  const handleUndo = () => {
    void undo().catch((cause: unknown) => {
      const code = cause instanceof ApiError ? cause.code : null;
      const message =
        code === INTEREST_ERRORS.MATCH_NOTIFIED
          ? 'Ce match est déjà annoncé au candidat : il ne peut plus être annulé.'
          : code === INTEREST_ERRORS.UNDO_EXPIRED
            ? "Le délai d'annulation est passé."
            : cause instanceof Error
              ? cause.message
              : 'Annulation impossible.';

      Alert.alert('Annulation impossible', message);
    });
  };

  if (offersPending) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator color={colors.accent} />
      </View>
    );
  }

  return (
    <View
      style={[
        styles.screen,
        { backgroundColor: colors.background, paddingTop: insets.top + spacing.md },
      ]}
    >
      <View style={styles.header}>
        <View style={styles.headerText}>
          <Text variant="hero">Découvrir</Text>
          <Pressable
            onPress={() => setChoosingOffer(true)}
            accessibilityRole="button"
            accessibilityLabel="Changer d'offre"
            hitSlop={spacing.xs}
            style={styles.offerLine}
          >
            <Text variant="small" tone="muted" numberOfLines={1} style={styles.offerName}>
              {offer ? offer.title : 'Aucune offre sélectionnée'}
            </Text>
            <Ionicons name="chevron-down" size={14} color={colors.textMuted} />
          </Pressable>
        </View>
        <HeaderButton
          icon="albums-outline"
          label="Changer d'offre"
          onPress={() => setChoosingOffer(true)}
        />
      </View>

      <View style={styles.content}>
        {offer === null ? (
          <EmptyState
            title={
              offers.length === 0
                ? 'Publie une offre pour découvrir des candidats'
                : 'Aucune offre prête'
            }
            description={
              offers.length === 0
                ? "L'offre express suffit : un intitulé, un contrat, une commune. Le reste se complète plus tard."
                : 'Une offre doit être publiée et avoir un code postal pour proposer des candidats.'
            }
            action={{
              label: 'Créer une offre express',
              onPress: () => router.push('/organisation/offre-express'),
            }}
          />
        ) : status === 'ready' ? (
          <SwipeDeck
            cards={deck}
            keyOf={(card) => String(card.id)}
            renderCard={(card) => <CandidateCardFace candidate={card} />}
            onSwipe={handleSwipe}
            onOpen={(card) => setOpened(card)}
            onUndo={canUndo ? handleUndo : undefined}
          />
        ) : status === 'loading' ? (
          <View style={styles.centered}>
            <ActivityIndicator color={colors.accent} />
            <Text variant="small" tone="muted">
              On prépare ta sélection…
            </Text>
          </View>
        ) : status === 'error' ? (
          <DeckError error={error} onRetry={() => void refresh()} />
        ) : (
          <EmptyState
            title="Tu as vu tous les profils"
            description="De nouveaux candidats s'inscrivent chaque semaine. Reviens bientôt, ou essaie avec une autre offre."
            action={{ label: "Changer d'offre", onPress: () => setChoosingOffer(true) }}
          />
        )}
      </View>

      {choosingOffer ? (
        <OfferPickerSheet
          offers={offers}
          selectedId={offerId}
          onSelect={(chosen: JobOffer) => {
            setChosenId(chosen.id);
            setChoosingOffer(false);
          }}
          onCreateExpress={() => {
            setChoosingOffer(false);
            router.push('/organisation/offre-express');
          }}
          onClose={() => setChoosingOffer(false)}
        />
      ) : null}

      {opened ? (
        <CandidateDetailSheet candidate={opened} onClose={() => setOpened(null)} />
      ) : null}

      {match ? (
        <MatchSheet
          counterpartLabel={match.label}
          applicationSent={match.applicationSent}
          onSeeMatches={() => {
            setMatch(null);
            router.push('/matchs');
          }}
          onContinue={() => setMatch(null)}
        />
      ) : null}
    </View>
  );
}

/**
 * Message d'echec d'un « Ca m'interesse ».
 *
 * Chaque code dit une chose differente a l'employeur, et une seule d'entre
 * elles se repare depuis cet ecran. Les confondre dans un message generique
 * ferait chercher un probleme de reseau la ou il y a un quota atteint.
 */
function signalerEchecInteret(cause: unknown): void {
  const code = cause instanceof ApiError ? cause.code : null;

  if (code === INTEREST_ERRORS.QUOTA) {
    Alert.alert(
      'Quota du jour atteint',
      "Tu as utilisé tes 30 « Ça m'intéresse » du jour pour cette offre. Reviens demain.",
    );

    return;
  }
  if (code === INTEREST_ERRORS.CANDIDATE_GONE) {
    Alert.alert(
      'Profil indisponible',
      "Ce candidat n'est plus disponible pour cette offre.",
    );

    return;
  }
  if (code === INTEREST_ERRORS.OFFER_UNPUBLISHED || code === INTEREST_ERRORS.CLOSED) {
    Alert.alert('Offre indisponible', "Cette offre n'accepte plus de mise en relation.");

    return;
  }

  Alert.alert(
    'Geste non enregistré',
    cause instanceof Error ? cause.message : 'Réessaie dans un instant.',
  );
}

/**
 * Les refus du serveur qui se reparent en deux gestes (publier, saisir un
 * code postal) meritent leur propre ecran : les noyer dans « Impossible de
 * charger » enverrait l'employeur chercher un probleme de reseau.
 */
function DeckError({ error, onRetry }: { error: unknown; onRetry: () => void }) {
  const router = useRouter();
  const code = error instanceof ApiError ? error.code : null;

  if (code === INTEREST_ERRORS.OFFER_NOT_LOCATED) {
    return (
      <EmptyState
        title="Il manque le code postal"
        description="Sans lui, on ne peut pas savoir quels candidats peuvent venir travailler chez toi."
        action={{ label: "Compléter l'offre", onPress: () => router.push('/mes-offres') }}
      />
    );
  }
  if (code === INTEREST_ERRORS.OFFER_UNPUBLISHED) {
    return (
      <EmptyState
        title="Publie cette offre"
        description="Une offre en brouillon n'a pas de page publique : un candidat prévenu tomberait sur une offre introuvable."
        action={{
          label: 'Aller à mes offres',
          onPress: () => router.push('/mes-offres'),
        }}
      />
    );
  }
  if (code === INTEREST_ERRORS.NOT_OPEN_HERE) {
    return (
      <EmptyState
        title="Pas encore ouvert ici"
        description="Découvrir démarre dans les Pyrénées-Orientales. On ouvre les autres départements au fur et à mesure."
      />
    );
  }

  return (
    <EmptyState
      title="Impossible de charger les profils"
      description={error instanceof Error ? error.message : undefined}
      action={{ label: 'Réessayer', onPress: onRetry }}
    />
  );
}

function labelOf(card: DeckCandidateCard): string {
  return [card.first_name, card.last_name_initial ? `${card.last_name_initial}.` : null]
    .filter(Boolean)
    .join(' ');
}

function HeaderButton({
  icon,
  label,
  onPress,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  label: string;
  onPress: () => void;
}) {
  const { colors } = useTheme();

  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={label}
      hitSlop={spacing.xs}
      style={({ pressed }) => [
        styles.headerButton,
        {
          backgroundColor: colors.surface,
          borderColor: colors.border,
          opacity: pressed ? 0.7 : 1,
        },
      ]}
    >
      <Ionicons name={icon} size={22} color={colors.text} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, paddingHorizontal: spacing.xl },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    marginBottom: spacing.md,
  },
  headerText: { flex: 1, gap: 2 },
  offerLine: { flexDirection: 'row', alignItems: 'center', gap: spacing.xs },
  offerName: { flexShrink: 1 },
  headerButton: {
    width: 44,
    height: 44,
    borderRadius: 22,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  content: { flex: 1, paddingBottom: spacing.md },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: spacing.md },
});
