import { Ionicons } from '@expo/vector-icons';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Alert, Modal, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Text } from '@/components/ui/text';
import { DISCOVER_KEY } from '@/hooks/use-discover-deck';
import { MATCHES_KEY } from '@/hooks/use-matches';
import { ApiError } from '@/lib/api/client';
import {
  blockTarget,
  reportTarget,
  MODERATION_ERRORS,
  REPORT_REASONS,
  type ModerationTarget,
  type ReportContext,
} from '@/lib/api/moderation';
import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

// Signaler ou bloquer, depuis n'importe quelle carte et n'importe quel match
// (MOBILE.md §7).
//
// DEUX GESTES DIFFERENTS, PAS UN SEUL. Signaler s'adresse a l'equipe :
// « regardez ça ». Bloquer s'adresse à soi : « je ne veux plus voir cette
// personne ». Les confondre — bloquer automatiquement ce qu'on signale —
// ferait disparaître la preuve sous les yeux de celui qui signale, et
// empêcherait de signaler quelqu'un qu'on veut continuer à voir (une offre
// qu'on garde, un match en cours).
//
// La feuille les propose donc l'un après l'autre, et le blocage est une case
// à cocher facultative dans le même envoi : deux appels, un seul geste.
//
// ACCESSIBLE DE PARTOUT, et c'est une exigence d'Apple autant qu'une
// évidence produit : un jeune de seize ans qui tombe sur quelque chose de
// déplacé ne doit pas avoir à chercher où le dire.

export interface ReportSheetProps {
  /** Ouverte quand la cible est renseignée. */
  target: ModerationTarget | null;
  context: ReportContext;
  /** Nom affiché de la personne ou de l'offre visée, pour que l'écran soit clair. */
  label: string;
  /** Le blocage est proposé seulement là où il a un sens (une personne, pas une offre). */
  canBlock?: boolean;
  onClose: () => void;
}

export function ReportSheet({
  target,
  context,
  label,
  canBlock = true,
  onClose,
}: ReportSheetProps) {
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();
  const queryClient = useQueryClient();

  const [reason, setReason] = useState<string | null>(null);
  const [details, setDetails] = useState('');
  const [alsoBlock, setAlsoBlock] = useState(false);

  const envoi = useMutation({
    mutationFn: async () => {
      if (target === null || reason === null) return;

      await reportTarget({ context, reason, details, target });

      // Le blocage part APRÈS le signalement : si le second échoue, l'équipe
      // a quand même reçu le premier. L'inverse perdrait le signalement pour
      // une erreur qui ne le concerne pas.
      if (alsoBlock) await blockTarget(target);
    },
    onSuccess: () => {
      if (alsoBlock) {
        // Les deux piles et les matchs excluent les comptes bloqués : ce qui
        // est affiché est désormais faux.
        void queryClient.invalidateQueries({ queryKey: DISCOVER_KEY });
        void queryClient.invalidateQueries({ queryKey: MATCHES_KEY });
      }
      fermer();
      Alert.alert(
        'Merci',
        alsoBlock
          ? 'Signalement transmis. Vous ne vous verrez plus.'
          : 'Signalement transmis à notre équipe. On le traite sous 24 h.',
      );
    },
    onError: (error: unknown) => {
      const code = error instanceof ApiError ? error.code : null;

      if (code === MODERATION_ERRORS.ALREADY_SENT) {
        fermer();
        Alert.alert('Déjà transmis', 'Notre équipe a déjà ton signalement et le traite.');

        return;
      }
      Alert.alert(
        'Envoi impossible',
        error instanceof Error ? error.message : 'Réessaie dans un instant.',
      );
    },
  });

  const fermer = () => {
    setReason(null);
    setDetails('');
    setAlsoBlock(false);
    onClose();
  };

  return (
    <Modal
      visible={target !== null}
      transparent
      animationType="slide"
      onRequestClose={fermer}
      statusBarTranslucent
    >
      <Pressable
        style={styles.backdrop}
        onPress={fermer}
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
        <View style={styles.heading}>
          <Ionicons name="flag-outline" size={22} color={colors.danger} />
          <View style={styles.headingText}>
            <Text variant="sectionTitle">Signaler</Text>
            <Text variant="small" tone="muted" numberOfLines={1}>
              {label}
            </Text>
          </View>
        </View>

        <ScrollView style={styles.list} contentContainerStyle={styles.listContent}>
          {REPORT_REASONS.map((option) => {
            const actif = reason === option.value;

            return (
              <Pressable
                key={option.value}
                onPress={() => setReason(option.value)}
                accessibilityRole="radio"
                accessibilityState={{ selected: actif }}
                style={({ pressed }) => [
                  styles.reason,
                  {
                    backgroundColor: actif ? colors.surfaceMuted : 'transparent',
                    borderColor: actif ? colors.accent : colors.border,
                    opacity: pressed ? 0.7 : 1,
                  },
                ]}
              >
                <Text variant="body" style={styles.reasonText}>
                  {option.label}
                </Text>
                {actif ? (
                  <Ionicons name="checkmark-circle" size={20} color={colors.accent} />
                ) : null}
              </Pressable>
            );
          })}

          <Field
            label="Ce qui s'est passé (facultatif)"
            value={details}
            onChangeText={setDetails}
            placeholder="Quelques mots aident notre équipe à comprendre."
            multiline
            numberOfLines={3}
            maxLength={2000}
          />

          {canBlock ? (
            <Pressable
              onPress={() => setAlsoBlock(!alsoBlock)}
              accessibilityRole="checkbox"
              accessibilityState={{ checked: alsoBlock }}
              accessibilityLabel="Bloquer aussi"
              style={[styles.block, { borderColor: colors.border }]}
            >
              <Ionicons
                name={alsoBlock ? 'checkbox' : 'square-outline'}
                size={22}
                color={alsoBlock ? colors.accent : colors.textMuted}
              />
              <View style={styles.blockText}>
                <Text variant="bodyStrong">Bloquer aussi</Text>
                <Text variant="small" tone="muted">
                  Vous ne vous verrez plus, ni dans Découvrir, ni dans les matchs.
                </Text>
              </View>
            </Pressable>
          ) : null}
        </ScrollView>

        <Button
          label="Envoyer le signalement"
          disabled={reason === null}
          loading={envoi.isPending}
          onPress={() => envoi.mutate()}
        />
        <Button label="Annuler" variant="ghost" onPress={fermer} />
      </View>
    </Modal>
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
    maxHeight: '88%',
  },
  heading: { flexDirection: 'row', alignItems: 'center', gap: spacing.md },
  headingText: { flex: 1, gap: 2 },
  list: { flexGrow: 0 },
  listContent: { gap: spacing.sm, paddingVertical: spacing.xs },
  reason: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.md,
    borderRadius: radii.md,
    borderWidth: 1,
  },
  reasonText: { flex: 1 },
  block: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: spacing.sm,
    padding: spacing.md,
    borderRadius: radii.md,
    borderWidth: 1,
    marginTop: spacing.xs,
  },
  blockText: { flex: 1, gap: 2 },
});
