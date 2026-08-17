import { useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { ChevronDown, LogOut } from 'lucide-react';
import { useDismissableLayer } from '@/hooks/use-dismissable-layer';
import { accountLinksFor, initialsFromEmail, roleLabel } from '@/lib/account-links';

interface UserMenuProps {
  email: string;
  role: string;
  onLogout: () => void;
}

// Regroupe tout l'espace personnel (liens propres au role + deconnexion)
// derriere une seule pastille, au lieu des 5-6 boutons qui s'alignaient
// dans la barre et la rendaient illisible une fois connecte en
// entreprise/CFA. Ouverture au clic (pas au survol comme le menu Offres) :
// c'est un menu d'actions, un survol accidentel ne doit pas le declencher.
export function UserMenu({ email, role, onLogout }: UserMenuProps) {
  const [isOpen, setIsOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const links = accountLinksFor(role);

  useDismissableLayer({
    isOpen,
    containerRef,
    onDismiss: (source) => {
      // Le focus doit etre lu AVANT la fermeture : une fois le panneau
      // demonte, document.activeElement est deja retombe sur <body>.
      const focusWasInside = !!containerRef.current?.contains(document.activeElement);
      setIsOpen(false);
      // Ne rapatrier le focus que s'il etait dans le menu. Sinon un Echap
      // tape dans un champ de la page (l'ecouteur est pose sur tout le
      // document) arrachait le curseur pour l'envoyer sur la pastille de la
      // barre, en plein milieu d'une saisie.
      if (source === 'escape' && focusWasInside) {
        triggerRef.current?.focus();
      }
    },
  });

  return (
    <div
      ref={containerRef}
      className="relative"
      // Ferme le menu quand la tabulation en sort : sans ca, le panneau
      // restait affiche (et aria-expanded a true) alors que le focus etait
      // deja reparti dans la page. Meme garde que handleOffersBlur pour le
      // menu Offres — relatedTarget est l'element qui recoit le focus.
      onBlur={(event) => {
        if (!event.currentTarget.contains(event.relatedTarget as Node)) {
          setIsOpen(false);
        }
      }}
    >
      <button
        ref={triggerRef}
        type="button"
        onClick={() => setIsOpen((current) => !current)}
        aria-haspopup="menu"
        aria-expanded={isOpen}
        className="flex items-center gap-2 rounded-full border border-border py-1 pl-1 pr-2 transition-colors hover:bg-accent"
      >
        <span
          aria-hidden="true"
          className="flex h-7 w-7 items-center justify-center rounded-full bg-jeuncy-gradient font-poppins text-xs font-bold text-white"
        >
          {initialsFromEmail(email)}
        </span>
        <span className="hidden max-w-[9rem] truncate font-inter text-sm text-foreground/80 lg:inline">
          {email}
        </span>
        <ChevronDown
          className={`h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200 ${
            isOpen ? 'rotate-180' : ''
          }`}
          aria-hidden="true"
        />
        <span className="sr-only">Mon compte</span>
      </button>

      {isOpen && (
        <div
          role="menu"
          aria-label="Mon compte"
          // z-50 comme le panneau de notifications : meme couche, plus de
          // hierarchie implicite ou l'un passe systematiquement sous l'autre.
          className="animate-in fade-in slide-in-from-top-1 absolute right-0 top-full z-50 mt-2 w-60 overflow-hidden rounded-md border border-border bg-popover shadow-lg duration-150"
        >
          <div className="border-b border-border px-3 py-2.5">
            <p className="truncate font-inter text-sm font-medium text-foreground">
              {email}
            </p>
            <p className="font-inter text-xs text-muted-foreground">{roleLabel(role)}</p>
          </div>

          <div className="p-1.5">
            {links.map((link) => (
              <Link
                key={link.to}
                to={link.to}
                role="menuitem"
                onClick={() => setIsOpen(false)}
                className="flex items-center gap-2.5 rounded-md px-3 py-2 font-inter text-sm text-foreground/80 transition-colors hover:bg-accent hover:text-foreground"
              >
                <link.icon className="h-4 w-4 shrink-0" aria-hidden="true" />
                {link.label}
              </Link>
            ))}
          </div>

          <div className="border-t border-border p-1.5">
            <button
              type="button"
              role="menuitem"
              onClick={() => {
                setIsOpen(false);
                onLogout();
              }}
              className="flex w-full items-center gap-2.5 rounded-md px-3 py-2 font-inter text-sm text-destructive transition-colors hover:bg-destructive/10"
            >
              <LogOut className="h-4 w-4 shrink-0" aria-hidden="true" />
              Se déconnecter
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
