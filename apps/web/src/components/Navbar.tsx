import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Menu, X, ChevronDown } from 'lucide-react';
import { ContractType } from '@jeuncy/shared';
import { Button, buttonVariants } from '@/components/ui/button';
import { ThemeToggle } from '@/components/ThemeToggle';
import { NotificationBell } from '@/components/NotificationBell';
import { UserMenu } from '@/components/UserMenu';
import { useThemeStore } from '@/store/theme-store';
import { useAuthStore } from '@/store/auth-store';
import { logout as logoutRequest } from '@/lib/api/auth';
import { accountLinksFor, initialsFromEmail, roleLabel } from '@/lib/account-links';
import { cn } from '@/lib/utils';

// "À propos" a ete sorti de la barre (deplace en pied de page) : c'est une
// page de decouverte, pas un outil de navigation quotidien, et sa presence
// ici participait a l'encombrement signale.
// "Tarifs" retire de la barre le 2026-08-17 a la demande de l'equipe
// commerciale : afficher un prix avant tout echange refroidit un prospect qui
// n'a pas encore vu la valeur du service. La page /tarifs existe toujours et
// reste accessible par lien direct — elle est envoyee aux prospects apres un
// premier contact, et reste liee depuis l'espace connecte (candidatures
// verrouillees, acces CVtheque) ou l'interlocuteur est deja engage.
const NAV_LINKS = [
  { label: 'Offres', href: '/offres' },
  { label: 'Entreprises', href: '/entreprises' },
  { label: 'CFA', href: '/cfa' },
  { label: 'Contact', href: '/contact' },
];

// Sous-categories affichees au survol de l'onglet Offres (voir OFFERS_HREF
// ci-dessous) — memes valeurs que ContractType, param "contract_type" deja
// gere par JobOffers.tsx (useSearchParams).
const OFFERS_HREF = '/offres';
const OFFER_CATEGORIES = [
  { value: ContractType.ALTERNANCE, label: 'Alternance' },
  { value: ContractType.SAISONNIER, label: 'Saisonnier' },
  { value: ContractType.BENEVOLAT, label: 'Bénévolat' },
  { value: ContractType.JOB_ETUDIANT, label: 'Job étudiant' },
  { value: ContractType.STAGE, label: 'Stage' },
];

export function Navbar() {
  const theme = useThemeStore((state) => state.theme);
  const logoSrc = theme === 'dark' ? '/logo/logo-dark.png' : '/logo/logo-light.png';
  const user = useAuthStore((state) => state.user);
  const clearSession = useAuthStore((state) => state.clearSession);
  const navigate = useNavigate();
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [isOffersMenuOpen, setIsOffersMenuOpen] = useState(false);
  const offersCloseTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    return () => {
      if (offersCloseTimerRef.current) {
        clearTimeout(offersCloseTimerRef.current);
      }
    };
  }, []);

  function openOffersMenu() {
    if (offersCloseTimerRef.current) {
      clearTimeout(offersCloseTimerRef.current);
      offersCloseTimerRef.current = null;
    }
    setIsOffersMenuOpen(true);
  }

  // Petit delai avant fermeture (plutot qu'une fermeture instantanee au
  // moindre mouseleave) : sans lui, traverser l'ecart entre le lien et le
  // panneau suffisait a fermer le menu avant d'avoir pu cliquer une
  // sous-categorie — signale comme "complique d'acceder aux sous-categories".
  function scheduleCloseOffersMenu() {
    offersCloseTimerRef.current = setTimeout(() => setIsOffersMenuOpen(false), 250);
  }

  function handleOffersBlur(event: React.FocusEvent<HTMLDivElement>) {
    if (!event.currentTarget.contains(event.relatedTarget as Node)) {
      setIsOffersMenuOpen(false);
    }
  }

  async function handleLogout() {
    try {
      await logoutRequest();
    } finally {
      clearSession();
      navigate('/');
      setIsMobileMenuOpen(false);
    }
  }

  function closeMobileMenu() {
    setIsMobileMenuOpen(false);
  }

  return (
    <header className="sticky top-0 z-50 border-b border-border bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/75">
      <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4">
        {/* shrink-0 indispensable : la barre est tres chargee une fois
            connecte en entreprise/CFA (Mon entreprise, Mes offres, Visio,
            Paiements, Confidentialite...) et le logo, seul element sans
            largeur de texte, se faisait ecraser par flex jusqu'a devenir
            invisible. alt="" car le nom est deja porte par le texte a cote
            (evite une annonce en double aux lecteurs d'ecran). */}
        <Link
          to="/"
          className="flex shrink-0 items-center gap-2"
          onClick={closeMobileMenu}
        >
          <img src={logoSrc} alt="" className="h-11 w-11" />
          <span className="bg-jeuncy-gradient bg-clip-text font-poppins text-xl font-bold tracking-tight text-transparent">
            JEUNCY
          </span>
        </Link>

        <nav
          className="hidden items-center gap-6 md:flex"
          aria-label="Navigation principale"
        >
          {NAV_LINKS.map((link) =>
            link.href === OFFERS_HREF ? (
              <div
                key={link.href}
                className="group/offers relative"
                onMouseEnter={openOffersMenu}
                onMouseLeave={scheduleCloseOffersMenu}
                onBlur={handleOffersBlur}
              >
                <Link
                  to={link.href}
                  onFocus={openOffersMenu}
                  className="relative flex items-center gap-1 font-inter text-sm font-medium text-foreground/80 transition-colors hover:text-foreground"
                >
                  {link.label}
                  <ChevronDown
                    className="h-3.5 w-3.5 transition-transform duration-200 group-hover/offers:rotate-180"
                    aria-hidden="true"
                  />
                  <span className="absolute -bottom-1 left-0 h-0.5 w-0 rounded-full bg-jeuncy-gradient transition-all duration-300 group-hover/offers:w-full" />
                </Link>

                {isOffersMenuOpen && (
                  <div className="animate-in fade-in slide-in-from-top-1 absolute left-1/2 top-full z-10 mt-2 w-52 -translate-x-1/2 rounded-md border border-border bg-popover p-1.5 shadow-lg duration-150">
                    {OFFER_CATEGORIES.map((category) => (
                      <Link
                        key={category.value}
                        to={`${OFFERS_HREF}?contract_type=${category.value}`}
                        onClick={() => setIsOffersMenuOpen(false)}
                        className="block rounded-md px-3 py-2 font-inter text-sm text-foreground/80 transition-colors hover:bg-accent hover:text-foreground"
                      >
                        {category.label}
                      </Link>
                    ))}
                  </div>
                )}
              </div>
            ) : (
              <Link
                key={link.href}
                to={link.href}
                className="group relative font-inter text-sm font-medium text-foreground/80 transition-colors hover:text-foreground"
              >
                {link.label}
                <span className="absolute -bottom-1 left-0 h-0.5 w-0 rounded-full bg-jeuncy-gradient transition-all duration-300 group-hover:w-full" />
              </Link>
            ),
          )}
        </nav>

        <div className="flex items-center gap-2">
          <ThemeToggle />
          {user && <NotificationBell />}

          {/* Actions completes : visibles a partir de md, remplacees par le
              menu hamburger en dessous (voir panneau mobile plus bas).
              Une fois connecte, tout l'espace personnel tient dans une seule
              pastille (UserMenu) au lieu des 5-6 boutons alignes qui
              saturaient la barre. */}
          <div className="hidden items-center gap-2 md:flex">
            {user ? (
              <UserMenu email={user.email} role={user.role} onLogout={handleLogout} />
            ) : (
              <>
                <Link
                  to="/login"
                  className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }))}
                >
                  Se connecter
                </Link>
                <Link
                  to="/register"
                  className={cn(buttonVariants({ variant: 'gradient', size: 'sm' }))}
                >
                  Créer un compte
                </Link>
              </>
            )}
          </div>

          {/* h-11 w-11 (44px) et non h-9 : c'est le seuil de confort tactile
              recommande par Apple. En dessous, on vise a cote sur un ecran
              tenu d'une main — et c'est le bouton qui ouvre toute la
              navigation mobile. */}
          <button
            type="button"
            className="inline-flex h-11 w-11 items-center justify-center rounded-md text-foreground transition-transform active:scale-90 md:hidden"
            aria-label={isMobileMenuOpen ? 'Fermer le menu' : 'Ouvrir le menu'}
            aria-expanded={isMobileMenuOpen}
            onClick={() => setIsMobileMenuOpen((current) => !current)}
          >
            {isMobileMenuOpen ? (
              <X className="h-6 w-6 animate-in zoom-in-50 duration-200" />
            ) : (
              <Menu className="h-6 w-6 animate-in zoom-in-50 duration-200" />
            )}
          </button>
        </div>
      </div>

      {isMobileMenuOpen && (
        // Le panneau doit defiler dans lui-meme, pas deborder : la barre est
        // en sticky top-0, donc tout ce qui depasse le bas de l'ecran devient
        // definitivement inatteignable (le scroll de page ne le ramene pas).
        // Mesure : le menu connecte en entreprise reclame 681px, soit 113px
        // hors ecran sur un iPhone SE et 14px sur un iPhone SE 2/3 — c'est
        // "Se deconnecter" qui tombait dedans. dvh et non vh pour tenir compte
        // de la barre d'adresse mobile, qui rogne encore 50 a 90px.
        <nav
          className="animate-in slide-in-from-top-2 fade-in max-h-[calc(100dvh-4rem)] overflow-y-auto overscroll-contain border-t border-border bg-background duration-200 md:hidden"
          aria-label="Navigation mobile"
        >
          {/* min-h-[44px] sur chaque element tapable plutot qu'un padding
              calcule : la hauteur est garantie quelle que soit la taille de
              police, y compris si l'utilisateur agrandit le texte de son
              telephone. Toutes les entrees du menu partagent ce minimum —
              elles faisaient 36px, et les pastilles de categories 26px. */}
          <div className="flex flex-col gap-1 px-4 py-3">
            {NAV_LINKS.map((link) => (
              <div key={link.href}>
                <Link
                  to={link.href}
                  onClick={closeMobileMenu}
                  className="flex min-h-[44px] items-center rounded-md px-3 py-2 font-inter text-sm font-medium text-foreground/80 transition-colors hover:bg-accent hover:text-foreground"
                >
                  {link.label}
                </Link>
                {link.href === OFFERS_HREF && (
                  <div className="ml-2 flex flex-wrap gap-1.5 px-3 pb-2">
                    {OFFER_CATEGORIES.map((category) => (
                      <Link
                        key={category.value}
                        to={`${OFFERS_HREF}?contract_type=${category.value}`}
                        onClick={closeMobileMenu}
                        className="inline-flex min-h-[44px] items-center rounded-full border border-border px-4 font-inter text-sm text-foreground/70 transition-colors hover:border-primary hover:text-foreground"
                      >
                        {category.label}
                      </Link>
                    ))}
                  </div>
                )}
              </div>
            ))}

            <Link
              to="/a-propos"
              onClick={closeMobileMenu}
              className="flex min-h-[44px] items-center rounded-md px-3 py-2 font-inter text-sm font-medium text-foreground/80 transition-colors hover:bg-accent hover:text-foreground"
            >
              À propos
            </Link>

            <div className="my-2 border-t border-border" />

            {user ? (
              <>
                {/* Meme liste que le menu profil desktop (accountLinksFor) :
                    une seule source, pas de risque d'oublier un lien d'un
                    cote lors d'un futur ajout. */}
                <div className="mb-1 flex items-center gap-2.5 px-3 py-2">
                  <span
                    aria-hidden="true"
                    className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-jeuncy-gradient font-poppins text-xs font-bold text-white"
                  >
                    {initialsFromEmail(user.email)}
                  </span>
                  <div className="min-w-0">
                    <p className="truncate font-inter text-sm font-medium text-foreground">
                      {user.email}
                    </p>
                    <p className="font-inter text-xs text-muted-foreground">
                      {roleLabel(user.role)}
                    </p>
                  </div>
                </div>

                {accountLinksFor(user.role).map((link) => (
                  <Link
                    key={link.to}
                    to={link.to}
                    onClick={closeMobileMenu}
                    className="flex min-h-[44px] items-center gap-2.5 rounded-md px-3 py-2 font-inter text-sm font-medium text-foreground/80 transition-colors hover:bg-accent hover:text-foreground"
                  >
                    <link.icon className="h-4 w-4 shrink-0" aria-hidden="true" />
                    {link.label}
                  </Link>
                ))}

                <Button
                  variant="outline"
                  size="sm"
                  className="mx-3 mt-2 min-h-[44px]"
                  onClick={handleLogout}
                >
                  Se déconnecter
                </Button>
              </>
            ) : (
              <>
                <Link
                  to="/login"
                  onClick={closeMobileMenu}
                  className="flex min-h-[44px] items-center rounded-md px-3 py-2 font-inter text-sm font-medium text-foreground/80 transition-colors hover:bg-accent hover:text-foreground"
                >
                  Se connecter
                </Link>
                <Link
                  to="/register"
                  onClick={closeMobileMenu}
                  className={cn(
                    buttonVariants({ variant: 'gradient', size: 'sm' }),
                    'mx-3 mt-1 min-h-[44px]',
                  )}
                >
                  Créer un compte
                </Link>
              </>
            )}
          </div>
        </nav>
      )}
    </header>
  );
}
