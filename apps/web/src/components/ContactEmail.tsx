import { useContactEmail } from '@/hooks/use-contact-email';
import { cn } from '@/lib/utils';

// Affiche l'adresse de contact officielle, servie par l'API.
//
// Composant plutot qu'une constante partagee : l'adresse est une donnee de
// configuration serveur (CONTACT_EMAIL), elle doit pouvoir changer sans
// reconstruire ni redeployer le bundle frontend.
//
// Existe parce qu'elle etait codee en dur a quatre endroits — page Contact,
// mentions legales (x2), politique de confidentialite. Le jour ou elle a
// change, seule la page Contact a suivi : les pages legales continuaient
// d'afficher une adresse abandonnee, alors que c'est par elle qu'un candidat
// exerce ses droits RGPD.
//
// Pendant le chargement (une seule requete, mise en cache 5 min pour toute la
// session), on rend des points de suspension plutot qu'une adresse de repli :
// sur une page legale, une adresse fausse est un vrai probleme, un bref vide
// n'en est pas un.
export function ContactEmail({ className }: { className?: string }) {
  const email = useContactEmail();

  if (!email) {
    return (
      <span className={cn('text-muted-foreground', className)} aria-busy="true">
        …
      </span>
    );
  }

  return (
    <a
      href={`mailto:${email}`}
      className={cn('text-foreground underline-offset-2 hover:underline', className)}
    >
      {email}
    </a>
  );
}
