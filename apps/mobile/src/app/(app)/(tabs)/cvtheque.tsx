import { EmptyState } from '@/components/ui/empty-state';
import { Screen } from '@/components/ui/screen';
import { Text } from '@/components/ui/text';

// Provisoire : la CVtheque arrive avec le lot H.
export default function CvthequeScreen() {
  return (
    <Screen>
      <Text variant="hero">CVthèque</Text>
      <EmptyState
        title="Bientôt ici"
        description="La recherche de candidats et la consultation de leurs CV : dans une prochaine étape."
      />
    </Screen>
  );
}
