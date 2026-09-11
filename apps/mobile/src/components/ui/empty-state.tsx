import { StyleSheet, View } from 'react-native';

import { spacing } from '@/theme/typography';

import { Button } from './button';
import { Text } from './text';

export interface EmptyStateProps {
  title: string;
  description?: string;
  /** Action de sortie : reessayer, effacer les filtres, creer... */
  action?: { label: string; onPress: () => void };
}

// Ecran vide ou en erreur. Le message dit toujours QUOI FAIRE : une liste vide
// sans issue laisse l'utilisateur devant un ecran mort.
export function EmptyState({ title, description, action }: EmptyStateProps) {
  return (
    <View style={styles.wrapper}>
      <Text variant="sectionTitle" style={styles.centre}>
        {title}
      </Text>
      {description ? (
        <Text variant="body" tone="muted" style={styles.centre}>
          {description}
        </Text>
      ) : null}
      {action ? (
        <Button label={action.label} variant="secondary" onPress={action.onPress} />
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: {
    paddingVertical: spacing.xxl,
    paddingHorizontal: spacing.xl,
    gap: spacing.lg,
    alignItems: 'center',
  },
  centre: { textAlign: 'center' },
});
