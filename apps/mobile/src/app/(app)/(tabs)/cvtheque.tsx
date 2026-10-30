import { EmptyState } from '@/components/ui/empty-state';
import { Screen } from '@/components/ui/screen';
import { Text } from '@/components/ui/text';

// Onglet masque (href: null dans (tabs)/_layout.tsx) : la CVtheque mobile
// est prevue au lot 5. L'ecran n'appelle aucune route et n'affiche aucune
// donnee candidat ; il ne sert que si quelqu'un y arrive par un lien.
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
