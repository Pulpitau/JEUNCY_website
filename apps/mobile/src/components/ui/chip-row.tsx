import { Pressable, ScrollView, StyleSheet } from 'react-native';

import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

import { Text } from './text';

export interface ChipOption<T extends string> {
  value: T;
  label: string;
}

export interface ChipRowProps<T extends string> {
  options: readonly ChipOption<T>[];
  /** `null` = aucun filtre actif (la puce « Tous »). */
  value: T | null;
  onChange: (value: T | null) => void;
  allLabel?: string;
  accessibilityLabel: string;
}

// Rangee de filtres a puces, defilante horizontalement. Remplace les listes
// deroulantes du web : sur un telephone, un choix parmi cinq se fait plus vite
// d'un tap que par un menu qui s'ouvre. Retoucher la puce active la desactive.
export function ChipRow<T extends string>({
  options,
  value,
  onChange,
  allLabel = 'Tous',
  accessibilityLabel,
}: ChipRowProps<T>) {
  const { colors } = useTheme();

  const chips: { value: T | null; label: string }[] = [
    { value: null, label: allLabel },
    ...options,
  ];

  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={styles.row}
      accessibilityRole="radiogroup"
      accessibilityLabel={accessibilityLabel}
    >
      {chips.map((chip) => {
        const actif = chip.value === value;

        return (
          <Pressable
            key={chip.value ?? '__all__'}
            onPress={() => onChange(actif && chip.value !== null ? null : chip.value)}
            accessibilityRole="radio"
            accessibilityState={{ selected: actif, checked: actif }}
            style={[
              styles.chip,
              {
                backgroundColor: actif ? colors.accent : colors.surface,
                borderColor: actif ? colors.accent : colors.border,
              },
            ]}
          >
            <Text variant="label" tone={actif ? 'onAccent' : 'default'}>
              {chip.label}
            </Text>
          </Pressable>
        );
      })}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  row: {
    gap: spacing.sm,
    paddingVertical: spacing.xs,
  },
  chip: {
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.sm,
    borderRadius: radii.pill,
    borderWidth: 1,
    // Zone tactile confortable sans etre encombrante.
    minHeight: 36,
    justifyContent: 'center',
  },
});
