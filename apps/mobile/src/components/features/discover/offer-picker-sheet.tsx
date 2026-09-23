import { Ionicons } from '@expo/vector-icons';
import { Modal, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Text } from '@/components/ui/text';
import { whyNotDiscoverable } from '@/hooks/use-my-offers';
import type { JobOffer } from '@/lib/api/job-offers';
import { CONTRACT_TYPE_LABELS } from '@/lib/labels';
import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

// « Pour quelle offre ? » — le choix de la pile.
//
// Les offres qui ne peuvent pas en porter une (brouillon, code postal
// manquant) sont MONTREES, grisees, avec ce qui leur manque. Les cacher
// aurait ete plus simple et faux : une entreprise avec trois brouillons
// aurait lu « aucune offre » et cherche le bug ailleurs. Le defaut nomme se
// repare ; l'absence inexpliquee se subit.

export interface OfferPickerSheetProps {
  offers: readonly JobOffer[];
  selectedId: number | null;
  onSelect: (offer: JobOffer) => void;
  onCreateExpress: () => void;
  onClose: () => void;
}

export function OfferPickerSheet({
  offers,
  selectedId,
  onSelect,
  onCreateExpress,
  onClose,
}: OfferPickerSheetProps) {
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();

  return (
    <Modal
      visible
      transparent
      animationType="fade"
      onRequestClose={onClose}
      statusBarTranslucent
    >
      <Pressable
        style={styles.backdrop}
        onPress={onClose}
        accessibilityRole="button"
        accessibilityLabel="Fermer"
      />
      <View
        style={[
          styles.sheet,
          {
            backgroundColor: colors.surface,
            borderColor: colors.border,
            paddingBottom: insets.bottom + spacing.lg,
          },
        ]}
        accessibilityViewIsModal
      >
        <Text variant="title">Pour quelle offre ?</Text>
        <Text variant="small" tone="muted">
          Les candidats proposés dépendent du poste, du contrat et du lieu.
        </Text>

        <ScrollView style={styles.list} contentContainerStyle={styles.listContent}>
          {offers.length === 0 ? (
            <Text variant="small" tone="muted">
              Tu n&apos;as pas encore d&apos;offre. L&apos;offre express en crée une en
              une minute.
            </Text>
          ) : (
            offers.map((offer) => {
              const manque = whyNotDiscoverable(offer);

              return (
                <OfferRow
                  key={offer.id}
                  offer={offer}
                  missing={manque}
                  selected={offer.id === selectedId}
                  onPress={() => onSelect(offer)}
                />
              );
            })
          )}
        </ScrollView>

        <Button label="Créer une offre express" onPress={onCreateExpress} />
        <Button label="Fermer" variant="ghost" onPress={onClose} />
      </View>
    </Modal>
  );
}

function OfferRow({
  offer,
  missing,
  selected,
  onPress,
}: {
  offer: JobOffer;
  /** Vide si l'offre peut porter une pile ; sinon ce qui lui manque. */
  missing: string;
  selected: boolean;
  onPress: () => void;
}) {
  const { colors } = useTheme();
  const bloquee = missing !== '';

  return (
    <Pressable
      onPress={onPress}
      disabled={bloquee}
      accessibilityRole="button"
      accessibilityState={{ selected, disabled: bloquee }}
      accessibilityLabel={
        bloquee ? `${offer.title}. Indisponible : ${missing}` : offer.title
      }
      style={({ pressed }) => [
        styles.row,
        {
          backgroundColor: selected ? colors.surfaceMuted : 'transparent',
          borderColor: selected ? colors.accent : colors.border,
          opacity: bloquee ? 0.55 : pressed ? 0.7 : 1,
        },
      ]}
    >
      <View style={styles.rowText}>
        <Text variant="bodyStrong" numberOfLines={2}>
          {offer.title}
        </Text>
        <View style={styles.rowMeta}>
          <Text variant="small" tone="muted">
            {CONTRACT_TYPE_LABELS[offer.contract_type]}
            {offer.city ? ` · ${offer.city}` : ''}
          </Text>
        </View>
        {bloquee ? (
          <View style={styles.missing}>
            <Badge label={missing} tone="warm" />
          </View>
        ) : null}
      </View>
      {selected ? (
        <Ionicons name="checkmark-circle" size={22} color={colors.accent} />
      ) : null}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  backdrop: { flex: 1, backgroundColor: 'rgba(6, 29, 79, 0.45)' },
  sheet: {
    paddingHorizontal: spacing.xl,
    paddingTop: spacing.xl,
    gap: spacing.md,
    borderTopLeftRadius: radii.lg,
    borderTopRightRadius: radii.lg,
    borderTopWidth: 1,
    maxHeight: '80%',
  },
  list: { flexGrow: 0 },
  listContent: { gap: spacing.sm, paddingVertical: spacing.xs },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.md,
    borderRadius: radii.md,
    borderWidth: 1,
  },
  rowText: { flex: 1, gap: 2 },
  rowMeta: { flexDirection: 'row', gap: spacing.xs },
  missing: { marginTop: spacing.xs },
});
