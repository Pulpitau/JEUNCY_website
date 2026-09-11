import { StyleSheet, View } from 'react-native';

import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

import { Text } from './text';

export interface BadgeProps {
  label: string;
  /** `accent` pour un statut positif, `warm` pour une mise en avant, `neutral` par defaut. */
  tone?: 'neutral' | 'accent' | 'warm' | 'danger' | 'success';
}

export function Badge({ label, tone = 'neutral' }: BadgeProps) {
  const { colors } = useTheme();

  const palette = {
    neutral: { bg: colors.surfaceMuted, fg: colors.textMuted, border: colors.border },
    accent: { bg: colors.surface, fg: colors.accent, border: colors.accent },
    warm: { bg: colors.surface, fg: colors.accentWarm, border: colors.accentWarm },
    danger: { bg: colors.surface, fg: colors.danger, border: colors.danger },
    success: { bg: colors.surface, fg: colors.success, border: colors.success },
  }[tone];

  return (
    <View
      style={[styles.badge, { backgroundColor: palette.bg, borderColor: palette.border }]}
    >
      <Text variant="label" style={{ color: palette.fg }}>
        {label}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    alignSelf: 'flex-start',
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.xs,
    borderRadius: radii.pill,
    borderWidth: 1,
  },
});
