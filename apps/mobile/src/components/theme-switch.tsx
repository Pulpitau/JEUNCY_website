import { Pressable, StyleSheet, View } from 'react-native';

import { Text } from '@/components/ui/text';
import { useTheme, type ThemePreference } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

const OPTIONS: { value: ThemePreference; label: string }[] = [
  { value: 'system', label: 'Système' },
  { value: 'light', label: 'Clair' },
  { value: 'dark', label: 'Sombre' },
];

// Selecteur clair / sombre / systeme. "Système" est le defaut, et c'est
// volontaire : sur telephone, l'utilisateur a deja regle sa preference une
// fois pour toutes, l'application n'a pas a la lui redemander.
export function ThemeSwitch() {
  const { colors, preference, setPreference } = useTheme();

  return (
    <View style={styles.wrapper}>
      <Text variant="label" tone="muted">
        Apparence
      </Text>
      <View
        accessibilityRole="radiogroup"
        style={[styles.groupe, { backgroundColor: colors.surfaceMuted }]}
      >
        {OPTIONS.map((option) => {
          const actif = option.value === preference;

          return (
            <Pressable
              key={option.value}
              onPress={() => setPreference(option.value)}
              accessibilityRole="radio"
              accessibilityState={{ selected: actif, checked: actif }}
              accessibilityLabel={option.label}
              style={[
                styles.option,
                actif && { backgroundColor: colors.surface, borderColor: colors.accent },
              ]}
            >
              <Text variant="label" tone={actif ? 'accent' : 'muted'}>
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
  wrapper: { marginTop: spacing.xl, marginBottom: spacing.lg, gap: spacing.sm },
  groupe: {
    flexDirection: 'row',
    padding: spacing.xs,
    borderRadius: radii.pill,
    gap: spacing.xs,
  },
  option: {
    flex: 1,
    alignItems: 'center',
    paddingVertical: spacing.sm,
    borderRadius: radii.pill,
    borderWidth: 1,
    borderColor: 'transparent',
  },
});
