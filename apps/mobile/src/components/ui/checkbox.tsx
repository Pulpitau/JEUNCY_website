import { Pressable, StyleSheet, View } from 'react-native';

import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

import { Text } from './text';

export interface CheckboxProps {
  label: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
  error?: string;
}

export function Checkbox({ label, checked, onChange, error }: CheckboxProps) {
  const { colors } = useTheme();

  return (
    <View style={styles.wrapper}>
      <Pressable
        onPress={() => onChange(!checked)}
        accessibilityRole="checkbox"
        accessibilityState={{ checked }}
        accessibilityLabel={label}
        style={styles.row}
        // La zone tactile couvre le libelle entier, pas seulement la case :
        // viser un carre de 22 points au doigt est inutilement penible.
        hitSlop={spacing.sm}
      >
        <View
          style={[
            styles.box,
            {
              borderColor: error
                ? colors.danger
                : checked
                  ? colors.accent
                  : colors.border,
              backgroundColor: checked ? colors.accent : colors.surface,
            },
          ]}
        >
          {checked ? (
            <Text variant="small" tone="onAccent">
              ✓
            </Text>
          ) : null}
        </View>
        <Text variant="small" style={styles.label}>
          {label}
        </Text>
      </Pressable>
      {error ? (
        <Text variant="small" tone="danger" accessibilityLiveRegion="polite" role="alert">
          {error}
        </Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: { gap: spacing.xs },
  row: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.md },
  box: {
    width: 24,
    height: 24,
    borderWidth: 1.5,
    borderRadius: radii.sm,
    alignItems: 'center',
    justifyContent: 'center',
  },
  label: { flex: 1 },
});
