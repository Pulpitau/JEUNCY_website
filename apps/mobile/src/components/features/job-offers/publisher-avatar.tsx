import { Image } from 'expo-image';
import { StyleSheet, View } from 'react-native';

import { Text } from '@/components/ui/text';
import type { PublisherSummary } from '@/lib/api/job-offers';
import { useTheme } from '@/theme/theme-provider';
import { radii } from '@/theme/typography';

export interface PublisherAvatarProps {
  publisher: PublisherSummary | null;
  size?: number;
}

// Logo de l'entreprise ou du CFA, avec repli sur l'initiale du nom. Le web
// affiche une icone generique « batiment » ; l'initiale est plus parlante dans
// une liste ou dix cartes se suivent.
export function PublisherAvatar({ publisher, size = 32 }: PublisherAvatarProps) {
  const { colors } = useTheme();
  const dimension = { width: size, height: size, borderRadius: radii.sm };

  if (publisher?.logo_url) {
    return (
      <Image
        source={{ uri: publisher.logo_url }}
        style={[dimension, { borderWidth: 1, borderColor: colors.border }]}
        contentFit="contain"
        accessibilityLabel=""
      />
    );
  }

  const initiale = publisher?.name?.trim().charAt(0).toUpperCase() ?? '?';

  return (
    <View
      style={[
        styles.fallback,
        dimension,
        { backgroundColor: colors.surfaceMuted, borderColor: colors.border },
      ]}
    >
      <Text variant="label" tone="muted">
        {initiale}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  fallback: {
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
  },
});
