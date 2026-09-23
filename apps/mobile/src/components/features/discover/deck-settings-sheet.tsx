import { Ionicons } from '@expo/vector-icons';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Alert, Modal, Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Button } from '@/components/ui/button';
import { MultiChip } from '@/components/ui/multi-chip';
import { Text } from '@/components/ui/text';
import { CANDIDATE_PROFILE_KEY } from '@/hooks/use-candidate-profile';
import { useDeviceLocation } from '@/hooks/use-device-location';
import { DISCOVER_KEY } from '@/hooks/use-discover-deck';
import { updatePreferences } from '@/lib/api/candidate-profile';
import type { DeckMeta } from '@/lib/api/discover';
import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

// Réglages de « Découvrir » : jusqu'où chercher, et d'où.
//
// Remplace la feuille « Où ? » du prototype, qui demandait un numéro de
// département parce qu'aucun serveur ne savait encore où habitait le
// candidat. Le serveur le sait maintenant : le département n'est plus une
// saisie, c'est une conséquence.
//
// L'ÉLARGISSEMENT EST TOUJOURS ANNONCÉ (MOBILE.md §6). Quand le rayon ne
// donne rien, le serveur élargit tout seul au département, puis à la France.
// C'est le bon comportement — une pile vide n'aide personne — mais le
// candidat doit savoir qu'il regarde des offres à 400 km, sinon il croit
// que « 30 km » ne veut rien dire.

/** Paliers du rayon candidat : 5 à 100 km, bornes du serveur. */
const RADIUS_STEPS = [5, 10, 20, 30, 50, 100] as const;

const RADIUS_OPTIONS = RADIUS_STEPS.map((km) => ({
  value: String(km),
  label: `${km} km`,
}));

export interface DeckSettingsSheetProps {
  meta: DeckMeta | null;
  onClose: () => void;
}

export function DeckSettingsSheet({ meta, onClose }: DeckSettingsSheetProps) {
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();
  const queryClient = useQueryClient();
  const { enable, disable } = useDeviceLocation();

  const [rayon, setRayon] = useState(meta?.radius_km ?? 30);
  const gpsActif = meta?.location_source === 'DEVICE';

  const enregistrerRayon = useMutation({
    mutationFn: (km: number) => updatePreferences({ search_radius_km: km }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: CANDIDATE_PROFILE_KEY });
      void queryClient.invalidateQueries({ queryKey: DISCOVER_KEY });
      onClose();
    },
    onError: (error: Error) => Alert.alert('Rayon non enregistré', error.message),
  });

  const activerGps = () => {
    enable.mutate(undefined, {
      onSuccess: (resultat) => {
        if (resultat.ok) {
          onClose();

          return;
        }
        Alert.alert('Position non activée', resultat.message);
      },
      onError: (error: Error) =>
        Alert.alert(
          'Position indisponible',
          error.message || 'On continue avec la commune de ton profil.',
        ),
    });
  };

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
        <Text variant="title">Où chercher ?</Text>

        {meta ? <ScopeNotice meta={meta} /> : null}

        <MultiChip
          label="Jusqu'où je regarde les offres"
          options={RADIUS_OPTIONS}
          value={[String(rayon)]}
          onChange={(choisis) => {
            const suivant = choisis.find((km) => km !== String(rayon));
            if (suivant) setRayon(Number(suivant));
          }}
        />

        <View style={[styles.gps, { borderColor: colors.border }]}>
          <Ionicons
            name={gpsActif ? 'navigate' : 'navigate-outline'}
            size={20}
            color={gpsActif ? colors.accent : colors.textMuted}
          />
          <View style={styles.gpsText}>
            <Text variant="bodyStrong">
              {gpsActif ? 'Autour de moi (activé)' : 'Autour de moi'}
            </Text>
            <Text variant="small" tone="muted">
              {gpsActif
                ? 'Les distances partent de ta position actuelle.'
                : 'Utilise ta position plutôt que la commune de ton profil. Les recruteurs ne la voient jamais.'}
            </Text>
          </View>
        </View>

        {gpsActif ? (
          <Button
            label="Revenir à ma commune"
            variant="secondary"
            loading={disable.isPending}
            onPress={() =>
              disable.mutate(undefined, {
                onSuccess: onClose,
                onError: (error: Error) =>
                  Alert.alert('Action impossible', error.message),
              })
            }
          />
        ) : (
          <Button
            label="Utiliser ma position"
            variant="secondary"
            loading={enable.isPending}
            onPress={activerGps}
          />
        )}

        <Button
          label="Enregistrer"
          loading={enregistrerRayon.isPending}
          onPress={() => enregistrerRayon.mutate(rayon)}
        />
        <Button label="Fermer" variant="ghost" onPress={onClose} />
      </View>
    </Modal>
  );
}

/**
 * Ce que le serveur a réellement fait de la demande.
 *
 * Le dire est le point : sans ça, un candidat qui a réglé 30 km et reçoit des
 * offres de toute la France conclut que le réglage ne sert à rien.
 */
function ScopeNotice({ meta }: { meta: DeckMeta }) {
  const { colors } = useTheme();

  if (!meta.has_coordinates) {
    return (
      <Notice tone={colors.accentWarm} icon="alert-circle-outline">
        On ne sait pas encore où tu habites : complète ton code postal dans ton profil, ou
        active ta position ci-dessous.
      </Notice>
    );
  }

  if (meta.scope === 'radius') {
    return (
      <Notice tone={colors.success} icon="checkmark-circle-outline">
        Les offres affichées sont à moins de {meta.radius_km} km.
      </Notice>
    );
  }

  if (meta.scope === 'department') {
    return (
      <Notice tone={colors.accentWarm} icon="information-circle-outline">
        Rien à moins de {meta.radius_km} km : on a élargi à tout le département
        {meta.department ? ` ${meta.department}` : ''}.
      </Notice>
    );
  }

  return (
    <Notice tone={colors.accentWarm} icon="information-circle-outline">
      Rien près de chez toi pour l&apos;instant : on montre des offres de toute la France.
    </Notice>
  );
}

function Notice({
  tone,
  icon,
  children,
}: {
  tone: string;
  icon: keyof typeof Ionicons.glyphMap;
  children: React.ReactNode;
}) {
  const { colors } = useTheme();

  return (
    <View style={[styles.notice, { backgroundColor: colors.surfaceMuted }]}>
      <Ionicons name={icon} size={16} color={tone} />
      <Text variant="small" style={styles.noticeText}>
        {children}
      </Text>
    </View>
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
  },
  notice: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: spacing.sm,
    padding: spacing.md,
    borderRadius: radii.md,
  },
  noticeText: { flex: 1 },
  gps: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: spacing.sm,
    padding: spacing.md,
    borderRadius: radii.md,
    borderWidth: 1,
  },
  gpsText: { flex: 1, gap: 2 },
});
