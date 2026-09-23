import { StyleSheet, View, Pressable } from 'react-native';

import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

import { Text } from './text';

export interface MultiChipOption<T extends string> {
  value: T;
  label: string;
}

export interface MultiChipProps<T extends string> {
  label: string;
  options: readonly MultiChipOption<T>[];
  value: readonly T[];
  onChange: (value: T[]) => void;
  /** Au-dela, les puces non cochees deviennent inertes plutot que de disparaitre. */
  max?: number;
  hint?: string;
}

// Choix multiple a puces, en grille repliee plutot qu'en rangee defilante.
//
// Deux differences avec ChipRow, qui n'en fait pas un doublon : le choix est
// multiple, et la liste ne defile pas horizontalement. Seize secteurs dans
// une rangee qui defile, c'est douze secteurs que personne ne verra jamais.
//
// Au plafond (`max`), les puces non cochees restent VISIBLES mais inertes,
// avec le compteur qui explique pourquoi. Les masquer ferait croire a un
// bug ; ne rien dire ferait croire a un tap rate.
export function MultiChip<T extends string>({
  label,
  options,
  value,
  onChange,
  max,
  hint,
}: MultiChipProps<T>) {
  const { colors } = useTheme();
  const plafond = max !== undefined && value.length >= max;

  const basculer = (option: T) => {
    if (value.includes(option)) {
      onChange(value.filter((item) => item !== option));

      return;
    }
    if (plafond) return;
    onChange([...value, option]);
  };

  return (
    <View style={styles.wrapper}>
      <View style={styles.header}>
        <Text variant="label" tone="muted" style={styles.label}>
          {label}
        </Text>
        {max !== undefined ? (
          <Text variant="label" tone={plafond ? 'accent' : 'muted'}>
            {value.length}/{max}
          </Text>
        ) : null}
      </View>

      {hint ? (
        <Text variant="small" tone="muted">
          {hint}
        </Text>
      ) : null}

      <View accessibilityRole="list" style={styles.grid}>
        {options.map((option) => {
          const actif = value.includes(option.value);
          const inerte = plafond && !actif;

          return (
            <Pressable
              key={option.value}
              onPress={() => basculer(option.value)}
              disabled={inerte}
              accessibilityRole="checkbox"
              accessibilityState={{ checked: actif, disabled: inerte }}
              accessibilityLabel={option.label}
              style={[
                styles.chip,
                {
                  backgroundColor: actif ? colors.accent : colors.surface,
                  borderColor: actif ? colors.accent : colors.border,
                  opacity: inerte ? 0.4 : 1,
                },
              ]}
            >
              <Text variant="label" tone={actif ? 'onAccent' : 'default'}>
                {option.label}
              </Text>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: { gap: spacing.xs },
  header: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  label: { flex: 1 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  chip: {
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    borderRadius: radii.pill,
    borderWidth: 1,
    minHeight: 36,
    justifyContent: 'center',
  },
});
