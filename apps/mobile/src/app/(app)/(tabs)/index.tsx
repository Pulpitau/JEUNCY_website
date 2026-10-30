import { UserRole } from '@jeuncy/shared';
import * as WebBrowser from 'expo-web-browser';

import { BrandHeader } from '@/components/brand-header';
import { CandidateDeck } from '@/components/features/discover/candidate-deck';
import { EmployerDeck } from '@/components/features/discover/employer-deck';
import { VerificationGate } from '@/components/features/organization/verification-gate';
import { EmptyState } from '@/components/ui/empty-state';
import { Screen } from '@/components/ui/screen';
import { useOrganizationRole } from '@/hooks/use-organization';
import { useAuthStore } from '@/store/auth-store';

// Onglet « Decouvrir » : une pile de cartes des deux cotes, mais deux piles
// tres differentes derriere le meme mot.
//
// Candidat : des offres, une a la fois — la Selection du jour (au plus vingt
// offres Jeuncy) puis les offres partenaires, paginees. Branchee sur
// `discover/offers` depuis le lot 3 ; les gestes partent au serveur et un
// « Ca m'interesse » peut creer un match.
//
// Entreprise et CFA : des candidats, pour une offre donnee. La porte de
// verification est posee ici plutot que dans l'ecran : une entreprise non
// verifiee ne doit pas voir une seule carte, et c'est le premier endroit ou
// elle en verrait.
export default function DecouvrirScreen() {
  const organizationRole = useOrganizationRole();
  const role = useAuthStore((state) => state.user?.role);

  // Equipe Jeuncy (ADMIN, STAFF) : aucune pile n'a de sens pour elle, et la
  // pile candidat lui repondrait 403. L'administration vit sur le site.
  if (role === UserRole.ADMIN || role === UserRole.STAFF) {
    return (
      <Screen>
        <BrandHeader title="Découvrir" />
        <EmptyState
          title="Cet espace est réservé au site"
          description="L'administration de Jeuncy se fait sur jeuncy.com."
          action={{
            label: "Ouvrir l'administration",
            onPress: () => void WebBrowser.openBrowserAsync('https://jeuncy.com/admin'),
          }}
        />
      </Screen>
    );
  }

  if (organizationRole) {
    return (
      <VerificationGate>
        <EmployerDeck />
      </VerificationGate>
    );
  }

  return <CandidateDeck />;
}
