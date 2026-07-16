import { Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { ThemeToggle } from '@/components/ThemeToggle';
import { useThemeStore } from '@/store/theme-store';

const NAV_LINKS = [
  { label: 'Offres', href: '#offres' },
  { label: 'Entreprises', href: '#entreprises' },
  { label: 'CFA', href: '#cfa' },
];

export function Navbar() {
  const theme = useThemeStore((state) => state.theme);
  const logoSrc = theme === 'dark' ? '/logo/logo-dark.png' : '/logo/logo-light.png';

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
          {NAV_LINKS.map((link) => (
            <a
              key={link.href}
              href={link.href}
              className="font-inter text-sm font-medium text-foreground/80 transition-colors hover:text-foreground"
            >
              {link.label}
            </a>
          ))}
        </nav>

        <div className="flex items-center gap-2">
          <ThemeToggle />
          <Button variant="gradient" size="sm">
            Créer un compte
          </Button>
        </div>
      </div>
    </header>
  );
}
