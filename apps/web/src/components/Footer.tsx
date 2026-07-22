import { Link } from 'react-router-dom';

export function Footer() {
  return (
    <footer className="border-t border-border bg-background">
      <div className="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-10 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="font-poppins text-lg font-semibold text-foreground">Jeuncy</p>
          <p className="mt-1 font-inter text-sm text-muted-foreground">
            Ton alternance commence ici.
          </p>
          <p className="mt-6 font-inter text-xs text-muted-foreground">
            &copy; {new Date().getFullYear()} Jeuncy. Tous droits réservés.
          </p>
        </div>
        <nav
          className="flex gap-4 font-inter text-xs text-muted-foreground"
          aria-label="Liens légaux"
        >
          <Link
            to="/mentions-legales"
            className="transition-colors hover:text-foreground"
          >
            Mentions légales
          </Link>
          <Link to="/confidentialite" className="transition-colors hover:text-foreground">
            Politique de confidentialité
          </Link>
        </nav>
      </div>
    </footer>
  );
}
