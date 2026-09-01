import { Facebook, Instagram, Linkedin } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

// Icone TikTok dessinee a la main : lucide-react ne fournit pas de marque
// TikTok. Meme grille que les autres (24x24, trait de 2, bouts arrondis,
// pas de remplissage) pour que les quatre icones aient le meme poids
// visuel — une icone pleine au milieu de trois icones en trait se voit
// immediatement.
function TikTokIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={2}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5" />
    </svg>
  );
}

interface SocialLink {
  name: string;
  href: string;
  Icon: LucideIcon | typeof TikTokIcon;
}

// Comptes officiels Jeuncy. Retirer une entree ici suffit a faire
// disparaitre l'icone du pied de page : on n'affiche jamais un reseau dont
// le compte n'existe pas encore, un lien mort coute plus cher que l'icone
// absente.
const SOCIAL_LINKS: SocialLink[] = [
  { name: 'Instagram', href: 'https://www.instagram.com/jeuncy.fr/', Icon: Instagram },
  { name: 'TikTok', href: 'https://www.tiktok.com/@jeuncy1', Icon: TikTokIcon },
  {
    name: 'Facebook',
    href: 'https://www.facebook.com/profile.php?id=61593852037512',
    Icon: Facebook,
  },
  { name: 'LinkedIn', href: 'https://www.linkedin.com/company/jeuncy/', Icon: Linkedin },
];

export function SocialLinks() {
  return (
    <ul className="flex items-center gap-1" aria-label="Jeuncy sur les réseaux sociaux">
      {SOCIAL_LINKS.map(({ name, href, Icon }) => (
        <li key={name}>
          <a
            href={href}
            target="_blank"
            // noopener : empeche la page ouverte d'acceder a window.opener.
            // noreferrer : ne fuite pas l'URL de provenance au reseau social.
            rel="noopener noreferrer"
            // p-3 (12px) autour d'une icone de 20px donne 44px de cible
            // tactile, le minimum retenu pour le menu mobile.
            className="inline-flex items-center justify-center rounded-full p-3 text-muted-foreground transition-colors hover:text-jeuncy-coral focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
          >
            <Icon className="h-5 w-5" aria-hidden="true" />
            <span className="sr-only">Jeuncy sur {name}</span>
          </a>
        </li>
      ))}
    </ul>
  );
}
