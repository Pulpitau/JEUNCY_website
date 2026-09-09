import { LinearGradient } from 'expo-linear-gradient';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  View,
  type StyleProp,
  type ViewStyle,
} from 'react-native';

import { signatureGradient } from '@/theme/colors';
import { useTheme } from '@/theme/theme-provider';
import { radii, spacing, typeScale } from '@/theme/typography';

import { Text } from './text';

export interface ButtonProps {
  label: string;
  onPress: () => void;
  /** `primary` porte le degrade signature : un seul par ecran, sur l'action principale. */
  variant?: 'primary' | 'secondary' | 'ghost';
  loading?: boolean;
  disabled?: boolean;
  style?: StyleProp<ViewStyle>;
}

export function Button({
  label,
  onPress,
  variant = 'primary',
  loading = false,
  disabled = false,
  style,
}: ButtonProps) {
  const { colors } = useTheme();
  const inactif = disabled || loading;

  const contenu = (
    <>
      {loading ? (
        <ActivityIndicator
          color={variant === 'primary' ? colors.textOnAccent : colors.accent}
          style={styles.spinner}
        />
      ) : null}
      <Text variant="button" tone={variant === 'primary' ? 'onAccent' : 'accent'}>
        {label}
      </Text>
    </>
  );

  return (
    <Pressable
      onPress={onPress}
      disabled={inactif}
      accessibilityRole="button"
      accessibilityState={{ disabled: inactif, busy: loading }}
      // Zone tactile d'au moins 44 points de haut (recommandation Apple) :
      // la hauteur du bouton s'en charge, mais le retour visuel au toucher
      // doit rester perceptible.
      style={({ pressed }) => [{ opacity: inactif ? 0.55 : pressed ? 0.85 : 1 }, style]}
    >
      {variant === 'primary' ? (
        // Degrade signature reserve aux CTA (CLAUDE.md section 2).
        <LinearGradient
          colors={[...signatureGradient]}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 0 }}
          style={styles.base}
        >
          {contenu}
        </LinearGradient>
      ) : (
        <View
          style={[
            styles.base,
            variant === 'secondary'
              ? {
                  borderWidth: 1.5,
                  borderColor: colors.accent,
                  backgroundColor: 'transparent',
                }
              : { backgroundColor: 'transparent' },
          ]}
        >
          {contenu}
        </View>
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  base: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.sm,
    minHeight: 52,
    paddingHorizontal: spacing.xl,
    borderRadius: radii.pill,
  },
  spinner: {
    // Aligne le rond de chargement sur la ligne de base du libelle.
    height: typeScale.button.lineHeight,
  },
});
