import { forwardRef } from 'react';
import { StyleSheet, TextInput, View, type TextInputProps } from 'react-native';

import { useTheme } from '@/theme/theme-provider';
import { radii, spacing, typeScale } from '@/theme/typography';

import { Text } from './text';

export interface FieldProps extends TextInputProps {
  label: string;
  /** Message d'erreur de validation, affiche sous le champ. */
  error?: string;
}

// Champ de formulaire complet : libelle, saisie, erreur.
//
// L'accessibilite n'est pas un supplement (CLAUDE.md section 10) : le libelle
// est rattache au champ pour les lecteurs d'ecran, et l'erreur est annoncee
// quand elle apparait — l'equivalent natif du aria-live du web.
export const Field = forwardRef<TextInput, FieldProps>(function Field(
  { label, error, style, ...props },
  ref,
) {
  const { colors } = useTheme();

  return (
    <View style={styles.wrapper}>
      <Text variant="label" tone="muted">
        {label}
      </Text>
      <TextInput
        ref={ref}
        accessibilityLabel={label}
        accessibilityHint={error}
        placeholderTextColor={colors.textMuted}
        style={[
          styles.input,
          typeScale.body,
          {
            backgroundColor: colors.surface,
            color: colors.text,
            borderColor: error ? colors.danger : colors.border,
          },
          style,
        ]}
        {...props}
      />
      {error ? (
        <Text variant="small" tone="danger" accessibilityLiveRegion="polite" role="alert">
          {error}
        </Text>
      ) : null}
    </View>
  );
});

const styles = StyleSheet.create({
  wrapper: {
    gap: spacing.xs,
  },
  input: {
    minHeight: 50,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    borderWidth: 1,
    borderRadius: radii.md,
  },
});
