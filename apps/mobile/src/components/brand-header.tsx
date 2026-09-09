import { Image } from 'expo-image';
import { StyleSheet, View } from 'react-native';

import { Text } from '@/components/ui/text';
import { useTheme } from '@/theme/theme-provider';
import { spacing } from '@/theme/typography';

// Le logo existe en deux versions (fond clair / fond sombre) et ne doit jamais
// etre deforme, incline ni recolore (CLAUDE.md section 2) : d'ou le
// contentFit="contain" et une taille fixe plutot qu'un etirement.
const logos = {
  light: require('@/assets/logo/logo-light.png'),
  dark: require('@/assets/logo/logo-dark.png'),
};

export interface BrandHeaderProps {
  title: string;
  subtitle?: string;
}

export function BrandHeader({ title, subtitle }: BrandHeaderProps) {
  const { scheme } = useTheme();

  return (
    <View style={styles.wrapper}>
      <Image
        source={logos[scheme]}
        style={styles.logo}
        contentFit="contain"
        accessibilityLabel="Jeuncy"
      />
      <Text variant="hero">{title}</Text>
      {subtitle ? (
        <Text variant="body" tone="muted">
          {subtitle}
        </Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: {
    gap: spacing.sm,
    marginBottom: spacing.xl,
  },
  logo: {
    width: 64,
    height: 64,
    marginBottom: spacing.md,
  },
});
