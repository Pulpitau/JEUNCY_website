import { Link, useNavigate } from 'react-router-dom';
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

  async function handleLogout() {
    try {
      await logoutRequest();
    } finally {
      clearSession();
      navigate('/');
    }
  }

  return (
    <header className="sticky top-0 z-50 border-b border-border bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/75">
      <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4">
        <Link to="/" className="flex items-center gap-2">
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
                className="font-inter text-sm font-medium text-foreground/80 transition-colors hover:text-foreground"
              >
                {link.label}
              </Link>
            ) : (
              <a
                key={link.href}
                href={link.href}
                className="font-inter text-sm font-medium text-foreground/80 transition-colors hover:text-foreground"
              >
                {link.label}
              </a>
            ),
          )}
        </nav>

        <div className="flex items-center gap-2">
          <ThemeToggle />
          {user && <NotificationBell />}
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
              <span className="hidden font-inter text-sm text-muted-foreground sm:inline">
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
      </div>
    </header>
  );
}
