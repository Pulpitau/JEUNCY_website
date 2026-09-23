import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { Modal, Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Button } from '@/components/ui/button';
import { Text } from '@/components/ui/text';
import { palette, signatureGradient } from '@/theme/colors';
import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

// « C'est un match ! » — l'annonce, au moment du geste.
//
// Elle arrive tout de suite parce que le match est deja definitif : sans
// file d'attente, la notification et l'email partent dans l'appel qui le
// cree, et l'annulation du dernier geste refuse une ligne matchee dont
// l'autre partie est notifiee (MOBILE.md §5). Il n'y a donc RIEN a annuler
// ici, et aucun bouton ne doit le laisser croire.
//
// Ce que la feuille dit ensuite est le vrai enjeu du produit : la prochaine
// action appartient au candidat (envoyer son dossier). L'employeur n'a rien
// a faire, et le lui dire evite qu'il attende un ecran qui ne viendra pas.

export interface MatchSheetProps {
  /** Prenom + initiale, tel que la carte l'affiche. */
  counterpartLabel: string;
  /** Vrai quand le dossier existait deja : le match nait « dossier envoye ». */
  applicationSent: boolean;
  onSeeMatches: () => void;
  onContinue: () => void;
}

export function MatchSheet({
  counterpartLabel,
  applicationSent,
  onSeeMatches,
  onContinue,
}: MatchSheetProps) {
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();

  return (
    <Modal
      visible
      transparent
      animationType="fade"
      onRequestClose={onContinue}
      statusBarTranslucent
    >
      <Pressable
        style={styles.backdrop}
        onPress={onContinue}
        accessibilityRole="button"
        accessibilityLabel="Continuer"
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
        accessibilityLiveRegion="polite"
      >
        <LinearGradient
          colors={[...signatureGradient]}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 0 }}
          style={styles.badge}
        >
          <Ionicons name="heart" size={26} color={palette.white} />
        </LinearGradient>

        <Text variant="hero">C&apos;est un match !</Text>
        <Text variant="body" tone="muted">
          {applicationSent
            ? `${counterpartLabel} avait déjà envoyé son dossier : tu peux le lire dès maintenant.`
            : `${counterpartLabel} s'intéresse aussi à ton offre. On vient de le prévenir : c'est à lui d'envoyer son dossier.`}
        </Text>

        <Button
          label={applicationSent ? 'Voir le dossier' : 'Voir mes matchs'}
          onPress={onSeeMatches}
        />
        <Button label="Continuer à découvrir" variant="ghost" onPress={onContinue} />
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
  },
  badge: {
    width: 56,
    height: 56,
    borderRadius: 28,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
