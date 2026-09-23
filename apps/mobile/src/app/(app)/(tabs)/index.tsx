import { CandidateDeck } from '@/components/features/discover/candidate-deck';
import { EmployerDeck } from '@/components/features/discover/employer-deck';
import { VerificationGate } from '@/components/features/organization/verification-gate';
import { useOrganizationRole } from '@/hooks/use-organization';

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

  if (organizationRole) {
    return (
      <VerificationGate>
        <EmployerDeck />
      </VerificationGate>
    );
  }

  return <CandidateDeck />;
}
