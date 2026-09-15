import { EmptyState } from '@/components/ui/empty-state';
import { Screen } from '@/components/ui/screen';
import { Text } from '@/components/ui/text';

// Provisoire : la gestion des offres arrive avec le lot F.
export default function MesOffresScreen() {
  return (
    <Screen>
      <Text variant="hero">Mes offres</Text>
      <EmptyState
        title="Bientôt ici"
        description="Création, modification, publication et archivage de tes offres : prochaine étape."
      />
    </Screen>
  );
}
