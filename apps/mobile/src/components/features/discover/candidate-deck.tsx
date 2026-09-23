import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Alert, Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { DeckSettingsSheet } from '@/components/features/discover/deck-settings-sheet';
import { InterestSheet } from '@/components/features/discover/interest-sheet';
import { MatchSheet } from '@/components/features/discover/match-sheet';
import {
  JeuncyOfferCard,
  PartnerOfferCard,
} from '@/components/features/discover/offer-card';
import {
  SwipeDeck,
  type SwipeDirection,
} from '@/components/features/discover/swipe-deck';
import { EmptyState } from '@/components/ui/empty-state';
import { Text } from '@/components/ui/text';
import { useDiscoverDeck, type DeckCard } from '@/hooks/use-discover-deck';
import { ApiError } from '@/lib/api/client';
import { INTEREST_ERRORS } from '@/lib/api/interests';
import { KEPT_OFFERS_KEY } from '@/hooks/use-kept-offers';
import { useQueryClient } from '@tanstack/react-query';
import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

// « Découvrir » côté candidat, branché sur le serveur (MOBILE.md §3.2).
//
// Deux piles dans une seule : la Sélection du jour (offres Jeuncy, au plus
// vingt) puis les offres partenaires, paginées. Le candidat ne voit pas la
// couture — il voit des cartes, dont certaines portent un bandeau orange qui
// dit que la candidature se fera ailleurs.
//
// Le geste droit ouvre une feuille à deux boutons plutôt que d'agir seul :
// c'est la décision de fond du produit. Un swipe ne doit jamais envoyer une
// candidature à la place du candidat.

/** Durée d'affichage du bandeau de confirmation. */
const NOTICE_MS = 3500;

export function CandidateDeck() {
  const router = useRouter();
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();
  const queryClient = useQueryClient();

  const {
    deck,
    meta,
    status,
    error,
    canUndo,
    like,
    pass,
    decideExternal,
    undo,
    refresh,
  } = useDiscoverDeck();

  // Carte sortie à droite dont on attend la décision (feuille ouverte) :
  // retirée de la pile le temps de la feuille, remise si le candidat annule.
  const [held, setHeld] = useState<DeckCard | null>(null);
  const [busy, setBusy] = useState(false);
  // Le bandeau porte un identifiant en plus du texte : deux gestes de suite
  // avec le même message doivent relancer le compte à rebours, ce qu'une
  // chaîne identique ne ferait pas.
  const [notice, setNotice] = useState<{ text: string; id: number } | null>(null);
  const [match, setMatch] = useState<string | null>(null);
  const [settingsOpen, setSettingsOpen] = useState(false);

  useEffect(() => {
    if (!notice) return;
    const timer = setTimeout(() => setNotice(null), NOTICE_MS);

    return () => clearTimeout(timer);
  }, [notice]);

  const visibleDeck = held ? deck.filter((card) => card.key !== held.key) : deck;

  const annoncer = (text: string) => setNotice({ text, id: Date.now() });

  const handleSwipe = (card: DeckCard, direction: SwipeDirection) => {
    if (direction === 'right') {
      setHeld(card);

      return;
    }

    // « Passer ». Sur une offre Jeuncy le geste s'accumule et part par lot ;
    // sur une offre partenaire il part seul, faute de route de lot.
    if (card.kind === 'jeuncy') {
      pass(card.offer);

      return;
    }
    void decideExternal(card.offer, 'PASS').catch(() => {
      // Silencieux, comme le lot Jeuncy : « Passer » n'a rien promis.
    });
  };

  const handleOpen = (card: DeckCard) => {
    if (card.kind === 'jeuncy') {
      router.push({ pathname: '/offres/[id]', params: { id: String(card.offer.id) } });
    } else {
      router.push({
        pathname: '/offres/partenaire/[id]',
        params: { id: String(card.offer.id) },
      });
    }
  };

  const handleUndo = () => {
    void undo().catch((cause: unknown) => {
      const code = cause instanceof ApiError ? cause.code : null;
      Alert.alert(
        'Annulation impossible',
        code === INTEREST_ERRORS.MATCH_NOTIFIED
          ? "L'entreprise est déjà prévenue : ce match ne peut plus être annulé."
          : code === INTEREST_ERRORS.APPLICATION_ATTACHED
            ? 'Tu as déjà envoyé ton dossier sur cette offre. Retire-le depuis Candidatures.'
            : cause instanceof Error
              ? cause.message
              : 'Réessaie dans un instant.',
      );
    });
  };

  /** Interet sur une offre Jeuncy. `andApply` ouvre le formulaire ensuite. */
  const marquerInteret = (card: DeckCard & { kind: 'jeuncy' }, andApply: boolean) => {
    setBusy(true);
    void like(card.offer)
      .then(({ matched }) => {
        setHeld(null);

        if (andApply) {
          // L'intérêt est enregistré même si le candidat referme le
          // formulaire sans envoyer : il a dit oui, la carte ne revient pas.
          router.push({
            pathname: '/offres/[id]/postuler',
            params: { id: String(card.offer.id) },
          });

          return;
        }

        if (matched) {
          setMatch(publisherName(card));

          return;
        }
        annoncer("C'est noté. On prévient l'entreprise.");
      })
      .catch((cause: unknown) => {
        setHeld(null);
        signalerEchec(cause);
      })
      .finally(() => setBusy(false));
  };

  const garder = async (card: DeckCard & { kind: 'lba' }) => {
    setBusy(true);
    try {
      await decideExternal(card.offer, 'KEEP');
      // La section « Gardées » de l'onglet Candidatures lit cette liste.
      void queryClient.invalidateQueries({ queryKey: KEPT_OFFERS_KEY });
      setHeld(null);
      annoncer('Offre gardée. Retrouve-la dans Candidatures.');
    } catch (cause) {
      setHeld(null);
      signalerEchec(cause);
    } finally {
      setBusy(false);
    }
  };

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
          <Text variant="small" tone="muted" numberOfLines={1}>
            {sousTitre(meta)}
          </Text>
        </View>
        <HeaderButton
          icon="search-outline"
          label="Rechercher une offre par liste"
          onPress={() => router.push('/offres/recherche')}
        />
        <HeaderButton
          icon="options-outline"
          label="Où chercher ?"
          onPress={() => setSettingsOpen(true)}
        />
      </View>

      {notice ? (
        <View
          style={[styles.notice, { backgroundColor: colors.surfaceMuted }]}
          accessibilityLiveRegion="polite"
        >
          <Ionicons name="checkmark-circle" size={18} color={colors.success} />
          <Text variant="small" style={styles.noticeText}>
            {notice.text}
          </Text>
        </View>
      ) : null}

      <View style={styles.content}>
        {status === 'ready' ? (
          <SwipeDeck
            cards={visibleDeck}
            keyOf={(card) => card.key}
            renderCard={(card) =>
              card.kind === 'jeuncy' ? (
                <JeuncyOfferCard offer={card.offer} />
              ) : (
                <PartnerOfferCard offer={card.offer} />
              )
            }
            onSwipe={handleSwipe}
            onOpen={handleOpen}
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
            title="Tu as tout vu pour aujourd'hui"
            description="De nouvelles offres arrivent chaque nuit. Reviens demain, ou élargis ta zone de recherche."
            action={{
              label: 'Élargir ma recherche',
              onPress: () => setSettingsOpen(true),
            }}
          />
        )}
      </View>

      <InterestSheet
        card={held}
        busy={busy}
        onSendDossier={() =>
          held?.kind === 'jeuncy' ? marquerInteret(held, true) : undefined
        }
        onMarkInterest={() =>
          held?.kind === 'jeuncy' ? marquerInteret(held, false) : undefined
        }
        onKeep={() => (held?.kind === 'lba' ? garder(held) : Promise.resolve())}
        onCancel={() => setHeld(null)}
      />

      {settingsOpen ? (
        <DeckSettingsSheet meta={meta} onClose={() => setSettingsOpen(false)} />
      ) : null}

      {match ? (
        <MatchSheet
          counterpartLabel={match}
          applicationSent={false}
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
 * Ce que la pile montre vraiment, en une ligne.
 *
 * L'élargissement est toujours annoncé (MOBILE.md §6) : un candidat qui a
 * réglé 30 km et reçoit des offres de toute la France doit le savoir, sinon
 * il conclut que le réglage ne sert à rien.
 */
function sousTitre(meta: ReturnType<typeof useDiscoverDeck>['meta']): string {
  if (!meta) return 'Sélection du jour';
  if (!meta.has_coordinates) return 'Sélection du jour · partout en France';

  if (meta.scope === 'radius')
    return `Sélection du jour · à moins de ${meta.radius_km} km`;
  if (meta.scope === 'department') {
    return `Sélection du jour · tout le département${meta.department ? ` ${meta.department}` : ''}`;
  }

  return 'Sélection du jour · partout en France';
}

function publisherName(card: DeckCard & { kind: 'jeuncy' }): string {
  return (
    card.offer.company?.name ?? card.offer.cfa_organization?.name ?? 'Cette entreprise'
  );
}

/** Chaque refus dit une chose différente, et une seule se répare ici. */
function signalerEchec(cause: unknown): void {
  const code = cause instanceof ApiError ? cause.code : null;

  if (code === INTEREST_ERRORS.QUOTA) {
    Alert.alert(
      'Quota du jour atteint',
      "Tu as utilisé tes « ça m'intéresse » du jour. Reviens demain : la sélection se renouvelle chaque nuit.",
    );

    return;
  }
  if (code === INTEREST_ERRORS.OFFER_UNPUBLISHED || code === INTEREST_ERRORS.CLOSED) {
    Alert.alert('Offre indisponible', "Cette offre vient d'être retirée.");

    return;
  }

  Alert.alert(
    'Geste non enregistré',
    cause instanceof Error ? cause.message : 'Réessaie dans un instant.',
  );
}

/**
 * Les gardes du parcours match ont chacune leur suite. Celle des 16 ans ne
 * se répare pas : le site accepte les inscriptions dès 15 ans, et c'est
 * précisément pour ça qu'un message générique serait incompréhensible.
 */
function DeckError({ error, onRetry }: { error: unknown; onRetry: () => void }) {
  const router = useRouter();
  const code = error instanceof ApiError ? error.code : null;

  // Frein d'urgence côté serveur (services.jeuncy.match_actif). Rien à
  // réparer ici : ce n'est pas le candidat qui est en cause, et l'écran doit
  // lui laisser une porte ouverte plutôt qu'un mur.
  if (code === 'MATCH_NOT_OPEN_YET') {
    return (
      <EmptyState
        title="Découvrir ouvre très bientôt"
        description="On prépare la sélection. En attendant, tu peux chercher des offres et postuler normalement."
        action={{
          label: 'Chercher une offre',
          onPress: () => router.push('/offres/recherche'),
        }}
      />
    );
  }

  if (code === 'CANDIDATE_PROFILE_REQUIRED') {
    return (
      <EmptyState
        title="Crée ton profil"
        description="Ton prénom, ton nom et ta date de naissance suffisent pour commencer."
        action={{
          label: 'Créer mon profil',
          onPress: () => router.push('/profil/informations'),
        }}
      />
    );
  }
  if (code === 'BIRTH_DATE_REQUIRED') {
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
  }
  if (code === 'MATCH_MIN_AGE') {
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
  }

  return (
    <EmptyState
      title="Impossible de charger ta sélection"
      description={error instanceof Error ? error.message : undefined}
      action={{ label: 'Réessayer', onPress: onRetry }}
    />
  );
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
  headerButton: {
    width: 44,
    height: 44,
    borderRadius: 22,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  notice: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    borderRadius: radii.md,
    marginBottom: spacing.md,
  },
  noticeText: { flex: 1 },
  content: { flex: 1, paddingBottom: spacing.md },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: spacing.md },
});
