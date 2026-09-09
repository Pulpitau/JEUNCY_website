import { Pressable, StyleSheet, View } from 'react-native';

import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

import { Text } from './text';

export interface Choice<T extends string> {
  value: T;
  label: string;
  hint?: string;
}

export interface ChoiceGroupProps<T extends string> {
  label: string;
  choices: readonly Choice<T>[];
  value: T;
  onChange: (value: T) => void;
}

// Selecteur a options visibles, plutot qu'une liste deroulante : le choix du
// type de compte oriente tout le parcours, il doit se lire d'un coup d'oeil.
// Role "radio" pour que VoiceOver annonce correctement l'option cochee.
export function ChoiceGroup<T extends string>({
  label,
  choices,
  value,
  onChange,
}: ChoiceGroupProps<T>) {
  const { colors } = useTheme();

  return (
    <View style={styles.wrapper}>
      <Text variant="label" tone="muted">
        {label}
      </Text>
      <View accessibilityRole="radiogroup" style={styles.options}>
        {choices.map((choice) => {
          const actif = choice.value === value;

          return (
            <Pressable
              key={choice.value}
              onPress={() => onChange(choice.value)}
              accessibilityRole="radio"
              accessibilityState={{ selected: actif, checked: actif }}
              accessibilityLabel={choice.label}
              accessibilityHint={choice.hint}
              style={[
                styles.option,
                {
                  backgroundColor: colors.surface,
                  borderColor: actif ? colors.accent : colors.border,
                  borderWidth: actif ? 2 : 1,
                },
              ]}
            >
              <Text variant="bodyStrong" tone={actif ? 'accent' : 'default'}>
                {choice.label}
              </Text>
              {choice.hint ? (
                <Text variant="small" tone="muted">
                  {choice.hint}
                </Text>
              ) : null}
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: { gap: spacing.sm },
  options: { gap: spacing.sm },
  option: {
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.lg,
    borderRadius: radii.md,
    gap: spacing.xs,
  },
});
