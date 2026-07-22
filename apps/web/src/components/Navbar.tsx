import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Menu, X } from 'lucide-react';
import { UserRole } from '@jeuncy/shared';
import { Button, buttonVariants } from '@/components/ui/button';
import { ThemeToggle } from '@/components/ThemeToggle';
import { NotificationBell } from '@/components/NotificationBell';
import { useThemeStore } from '@/store/theme-store';
import { useAuthStore } from '@/store/auth-store';
import { logout as logoutRequest } from '@/lib/api/auth';
import { cn } from '@/lib/utils';

const NAV_LINKS = [
  { label: 'Offres', href: '/offres' },
  { label: 'Entreprises', href: '/a-propos#entreprises' },
  { label: 'CFA', href: '/a-propos#cfa' },
  { label: 'À propos', href: '/a-propos' },
];

export function Navbar() {
  const theme = useThemeStore((state) => state.theme);
  const logoSrc = theme === 'dark' ? '/logo/logo-dark.png' : '/logo/logo-light.png';
  const user = useAuthStore((state) => state.user);
  const clearSession = useAuthStore((state) => state.clearSession);
  const navigate = useNavigate();
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

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
        <Link to="/" className="flex items-center gap-2" onClick={closeMobileMenu}>
          <img src={logoSrc} alt="Jeuncy" className="h-9 w-9" />
        </Link>

        <nav
          className="hidden items-center gap-6 md:flex"
          aria-label="Navigation principale"
        >
          {NAV_LINKS.map((link) =>
            link.href.startsWith('/') ? (
              <Link
                key={link.href}
                to={link.href}
                className="group relative font-inter text-sm font-medium text-foreground/80 transition-colors hover:text-foreground"
              >
                {link.label}
                <span className="absolute -bottom-1 left-0 h-0.5 w-0 rounded-full bg-jeuncy-gradient transition-all duration-300 group-hover:w-full" />
              </Link>
            ) : (
              <a
                key={link.href}
                href={link.href}
                className="group relative font-inter text-sm font-medium text-foreground/80 transition-colors hover:text-foreground"
              >
                {link.label}
                <span className="absolute -bottom-1 left-0 h-0.5 w-0 rounded-full bg-jeuncy-gradient transition-all duration-300 group-hover:w-full" />
              </a>
            ),
          )}
        </nav>

        <div className="flex items-center gap-2">
          <ThemeToggle />
          {user && <NotificationBell />}

          {/* Actions completes : visibles a partir de md, remplacees par le
              menu hamburger en dessous (voir panneau mobile plus bas). */}
          <div className="hidden items-center gap-2 md:flex">
            {user ? (
              <>
                {user.role === UserRole.CANDIDATE && (
                  <>
                    <Link
                      to="/profile"
                      className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }))}
                    >
                      Mon profil
                    </Link>
                    <Link
                      to="/mes-candidatures"
                      className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }))}
                    >
                      Mes candidatures
                    </Link>
                  </>
                )}
                {(user.role === UserRole.COMPANY || user.role === UserRole.CFA) && (
                  <>
                    <Link
                      to="/organization"
                      className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }))}
                    >
                      {user.role === UserRole.COMPANY ? 'Mon entreprise' : 'Mon CFA'}
                    </Link>
                    <Link
                      to="/mes-offres"
                      className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }))}
                    >
                      Mes offres
                    </Link>
                    <Link
                      to="/mes-visios"
                      className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }))}
                    >
                      Visio démo
                    </Link>
                    <Link
                      to="/mes-paiements"
                      className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }))}
                    >
                      Paiements
                    </Link>
                  </>
                )}
                {user.role === UserRole.ADMIN && (
                  <Link
                    to="/admin"
                    className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }))}
                  >
                    Administration
                  </Link>
                )}
                <span className="hidden font-inter text-sm text-muted-foreground lg:inline">
                  {user.email}
                </span>
                <Button variant="outline" size="sm" onClick={handleLogout}>
                  Se déconnecter
                </Button>
              </>
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

          <button
            type="button"
            className="inline-flex h-9 w-9 items-center justify-center rounded-md text-foreground transition-transform active:scale-90 md:hidden"
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
        <nav
          className="animate-in slide-in-from-top-2 fade-in border-t border-border bg-background duration-200 md:hidden"
          aria-label="Navigation mobile"
        >
          <div className="flex flex-col gap-1 px-4 py-3">
            {NAV_LINKS.map((link) =>
              link.href.startsWith('/') ? (
                <Link
                  key={link.href}
                  to={link.href}
                  onClick={closeMobileMenu}
                  className="rounded-md px-3 py-2 font-inter text-sm font-medium text-foreground/80 transition-colors hover:bg-accent hover:text-foreground"
                >
                  {link.label}
                </Link>
              ) : (
                <a
                  key={link.href}
                  href={link.href}
                  onClick={closeMobileMenu}
                  className="rounded-md px-3 py-2 font-inter text-sm font-medium text-foreground/80 transition-colors hover:bg-accent hover:text-foreground"
                >
                  {link.label}
                </a>
              ),
            )}

            <div className="my-2 border-t border-border" />

            {user ? (
              <>
                {user.role === UserRole.CANDIDATE && (
                  <>
                    <Link
                      to="/profile"
                      onClick={closeMobileMenu}
                      className="rounded-md px-3 py-2 font-inter text-sm font-medium text-foreground/80 transition-colors hover:bg-accent hover:text-foreground"
                    >
                      Mon profil
                    </Link>
                    <Link
                      to="/mes-candidatures"
                      onClick={closeMobileMenu}
                      className="rounded-md px-3 py-2 font-inter text-sm font-medium text-foreground/80 transition-colors hover:bg-accent hover:text-foreground"
                    >
                      Mes candidatures
                    </Link>
                  </>
                )}
                {(user.role === UserRole.COMPANY || user.role === UserRole.CFA) && (
                  <>
                    <Link
                      to="/organization"
                      onClick={closeMobileMenu}
                      className="rounded-md px-3 py-2 font-inter text-sm font-medium text-foreground/80 transition-colors hover:bg-accent hover:text-foreground"
                    >
                      {user.role === UserRole.COMPANY ? 'Mon entreprise' : 'Mon CFA'}
                    </Link>
                    <Link
                      to="/mes-offres"
                      onClick={closeMobileMenu}
                      className="rounded-md px-3 py-2 font-inter text-sm font-medium text-foreground/80 transition-colors hover:bg-accent hover:text-foreground"
                    >
                      Mes offres
                    </Link>
                    <Link
                      to="/mes-visios"
                      onClick={closeMobileMenu}
                      className="rounded-md px-3 py-2 font-inter text-sm font-medium text-foreground/80 transition-colors hover:bg-accent hover:text-foreground"
                    >
                      Visio démo
                    </Link>
                    <Link
                      to="/mes-paiements"
                      onClick={closeMobileMenu}
                      className="rounded-md px-3 py-2 font-inter text-sm font-medium text-foreground/80 transition-colors hover:bg-accent hover:text-foreground"
                    >
                      Paiements
                    </Link>
                  </>
                )}
                {user.role === UserRole.ADMIN && (
                  <Link
                    to="/admin"
                    onClick={closeMobileMenu}
                    className="rounded-md px-3 py-2 font-inter text-sm font-medium text-foreground/80 transition-colors hover:bg-accent hover:text-foreground"
                  >
                    Administration
                  </Link>
                )}
                <p className="px-3 py-1 font-inter text-xs text-muted-foreground">
                  {user.email}
                </p>
                <Button
                  variant="outline"
                  size="sm"
                  className="mx-3 mt-1"
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
                  className="rounded-md px-3 py-2 font-inter text-sm font-medium text-foreground/80 transition-colors hover:bg-accent hover:text-foreground"
                >
                  Se connecter
                </Link>
                <Link
                  to="/register"
                  onClick={closeMobileMenu}
                  className={cn(
                    buttonVariants({ variant: 'gradient', size: 'sm' }),
                    'mx-3 mt-1',
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
