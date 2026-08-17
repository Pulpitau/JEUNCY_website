import { Link } from 'react-router-dom';
import { ArrowRight } from 'lucide-react';

export function Footer() {
  return (
    <footer className="border-t border-border bg-background">
      <div className="mx-auto flex max-w-6xl flex-col gap-8 px-4 py-10 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="font-poppins text-lg font-semibold text-foreground">Jeuncy</p>
          <p className="mt-1 font-inter text-sm text-muted-foreground">
            Ton alternance commence ici.
          </p>

          {/* "À propos" vit desormais ici plutot que dans la barre de
              navigation : page de decouverte, consultee une fois, pas un
              outil quotidien. Traite en CTA pour rester bien visible malgre
              le deplacement. */}
          <Link
            to="/a-propos"
            className="group mt-6 inline-flex items-center gap-2 rounded-full border border-border bg-background px-5 py-2.5 font-poppins text-sm font-semibold text-foreground transition-all hover:border-transparent hover:bg-jeuncy-gradient hover:text-white hover:shadow-md"
          >
            À propos de nous
            <ArrowRight
              className="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1"
              aria-hidden="true"
            />
          </Link>

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
