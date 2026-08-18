import { useQuery } from '@tanstack/react-query';
import { getContactDetails } from '@/lib/api/contact';

// Adresse de contact unique, servie par l'API (CONTACT_EMAIL cote serveur).
//
// Existe parce que l'adresse etait codee en dur a quatre endroits — page
// Contact, mentions legales (x2), politique de confidentialite. Le jour ou
// elle a change, seule la page Contact a suivi : les pages legales
// continuaient d'afficher une adresse abandonnee, alors que c'est par elle
// qu'un candidat exerce ses droits RGPD. Une seule source, plus de derive
// possible.
//
// Pas de valeur de repli en dur : afficher une adresse potentiellement fausse
// est pire qu'afficher un espace vide une fraction de seconde. Les appelants
// gerent le cas null.
export function useContactEmail(): string | null {
  const { data } = useQuery({
    queryKey: ['contact-details'],
    queryFn: getContactDetails,
    staleTime: 5 * 60 * 1000,
  });

  return data?.email ?? null;
}
