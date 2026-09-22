import { Ionicons } from '@expo/vector-icons';
import { Redirect, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { DepartmentSheet } from '@/components/features/discover/department-sheet';
import { InterestSheet } from '@/components/features/discover/interest-sheet';
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
import { useOrganizationRole } from '@/hooks/use-organization';
import { useSwipeStore } from '@/store/swipe-store';
import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

// Onglet d'accueil du candidat : la pile « Decouvrir ». Une offre a la fois,
// glisser a droite si ca l'interesse, a gauche pour passer, tap pour lire la
// fiche. Prototype du 2026-09-22 : gestes enregistres sur le telephone
// seulement (swipe-store.ts), aucune entreprise prevenue — l'ecran le dit.
export default function DecouvrirScreen() {
  const organizationRole = useOrganizationRole();

  // L'accueil d'une entreprise ou d'un CFA, c'est la gestion de ses offres,
  // pas la pile du candidat (dont l'onglet lui est masque).
  if (organizationRole) return <Redirect href="/mes-offres" />;

  return <Decouvrir />;
}

/** Duree d'affichage du bandeau de confirmation. */
const NOTICE_MS = 3500;

function Decouvrir() {
  const router = useRouter();
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();

  const department = useSwipeStore((state) => state.department);
  const setDepartment = useSwipeStore((state) => state.setDepartment);
  const record = useSwipeStore((state) => state.record);
  const undo = useSwipeStore((state) => state.undo);
  const lastGesture = useSwipeStore((state) => state.lastGesture);

  const { deck, cards, status, error, refresh } = useDiscoverDeck(department);

  // Carte sortie a droite dont on attend la decision (feuille ouverte) :
  // retiree de la pile le temps de la feuille, remise si le candidat annule.
  const [held, setHeld] = useState<DeckCard | null>(null);
  // Le bandeau porte un identifiant en plus du texte : deux gestes de suite
  // avec le meme message (« Offre gardee » puis « Offre gardee ») doivent
  // relancer le compte a rebours, ce qu'une chaine identique ne ferait pas.
  const [notice, setNotice] = useState<{ text: string; id: number } | null>(null);
  const [choosingDepartment, setChoosingDepartment] = useState(false);

  useEffect(() => {
    if (!notice) return;
    const timer = setTimeout(() => setNotice(null), NOTICE_MS);

    return () => clearTimeout(timer);
  }, [notice]);

  const visibleDeck = held ? deck.filter((card) => card.key !== held.key) : deck;

  // Annulable seulement si la carte est encore chargee : sinon elle ne
  // pourrait pas revenir en tete de pile.
  const canUndo =
    lastGesture !== null &&
    held === null &&
    cards.some((card) => card.key === lastGesture.key);

  const handleSwipe = (card: DeckCard, direction: SwipeDirection) => {
    if (direction === 'left') {
      record({ key: card.key, decision: 'PASS' });

      return;
    }
    setHeld(card);
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

  const handleDone = (message: string | null) => {
    setHeld(null);
    setNotice(message ? { text: message, id: Date.now() } : null);
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
          <Text variant="small" tone="muted">
            Sélection du jour · département {department}
          </Text>
        </View>
        <HeaderButton
          icon="search-outline"
          label="Rechercher une offre par liste"
          onPress={() => router.push('/offres/recherche')}
        />
        <HeaderButton
          icon="options-outline"
          label="Changer de département"
          onPress={() => setChoosingDepartment(true)}
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
            onUndo={canUndo ? () => undo() : undefined}
          />
        ) : status === 'loading' ? (
          <View style={styles.centered}>
            <ActivityIndicator color={colors.accent} />
            <Text variant="small" tone="muted">
              On prépare ta sélection…
            </Text>
          </View>
        ) : status === 'error' ? (
          <EmptyState
            title="Impossible de charger les offres"
            description={error?.message}
            action={{ label: 'Réessayer', onPress: () => void refresh() }}
          />
        ) : (
          <EmptyState
            title={`Tu as tout vu dans le ${department}`}
            description="Reviens demain, de nouvelles offres arrivent chaque nuit. Ou change de département."
            action={{
              label: 'Changer de département',
              onPress: () => setChoosingDepartment(true),
            }}
          />
        )}
      </View>

      <InterestSheet card={held} onDone={handleDone} onCancel={() => setHeld(null)} />

      {choosingDepartment ? (
        <DepartmentSheet
          current={department}
          onClose={() => setChoosingDepartment(false)}
          onSubmit={(next) => {
            setDepartment(next);
            setChoosingDepartment(false);
          }}
        />
      ) : null}
    </View>
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
