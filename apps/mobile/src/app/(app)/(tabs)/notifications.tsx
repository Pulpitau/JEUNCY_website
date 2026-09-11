import { EmptyState } from '@/components/ui/empty-state';
import { Screen } from '@/components/ui/screen';
import { Text } from '@/components/ui/text';

// Provisoire : les notifications arrivent avec le lot D.
export default function NotificationsScreen() {
  return (
    <Screen>
      <Text variant="hero">Notifications</Text>
      <EmptyState
        title="Bientôt ici"
        description="Les offres qui te correspondent et les réponses à tes candidatures s'afficheront ici."
      />
    </Screen>
  );
}
