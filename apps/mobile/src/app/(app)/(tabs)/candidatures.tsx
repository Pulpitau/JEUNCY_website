import { EmptyState } from '@/components/ui/empty-state';
import { Screen } from '@/components/ui/screen';
import { Text } from '@/components/ui/text';

// Provisoire : le suivi des candidatures arrive avec le lot C.
export default function CandidaturesScreen() {
  return (
    <Screen>
      <Text variant="hero">Candidatures</Text>
      <EmptyState
        title="Bientôt ici"
        description="Le suivi de tes candidatures et de leur statut arrive dans la prochaine étape."
      />
    </Screen>
  );
}
