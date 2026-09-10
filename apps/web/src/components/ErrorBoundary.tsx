import { Component, type ErrorInfo, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';

/**
 * Filet de securite autour des pages.
 *
 * POURQUOI. Le 2026-09-10, l'onglet Statistiques de /admin a rendu tout le
 * site entierement blanc : le site attendait un champ que l'API, deployee
 * separement par FTP, ne renvoyait pas encore. Une erreur dans un seul
 * composant remonte jusqu'a la racine de React et demonte l'application
 * complete — l'utilisateur ne voit meme plus la navigation pour aller
 * ailleurs.
 *
 * Ce composant arrete la chute au niveau du contenu de page : la Navbar et le
 * Footer restent en place, donc l'utilisateur peut toujours naviguer. Et il
 * affiche un message lisible au lieu du vide, qui ne dit rien a personne.
 *
 * Volontairement une classe : React n'offre pas d'equivalent en hook,
 * componentDidCatch n'existe que sur les composants de classe.
 */
type Props = { children: ReactNode };
type State = { erreur: Error | null };

export class ErrorBoundary extends Component<Props, State> {
  state: State = { erreur: null };

  static getDerivedStateFromError(erreur: Error): State {
    return { erreur };
  }

  componentDidCatch(erreur: Error, infos: ErrorInfo) {
    // Pas de service de collecte d'erreurs sur ce projet : la console reste le
    // seul endroit ou lire la pile. Sans ce log, un ecran d'erreur propre
    // rendrait le diagnostic PLUS difficile qu'un ecran blanc.
    console.error('Erreur non rattrapee dans une page :', erreur, infos.componentStack);
  }

  render() {
    if (!this.state.erreur) {
      return this.props.children;
    }

    return (
      <main className="mx-auto flex max-w-xl flex-col items-start gap-4 px-4 py-16">
        <h1 className="font-poppins text-2xl font-bold">
          Cette page n'a pas pu s'afficher
        </h1>
        <p className="font-inter text-muted-foreground">
          Une erreur est survenue de notre côté. Le reste du site fonctionne : tu peux
          recharger cette page ou passer par le menu.
        </p>
        <div className="flex flex-wrap gap-3">
          <Button onClick={() => window.location.reload()}>Recharger la page</Button>
          <Button variant="outline" onClick={() => (window.location.href = '/')}>
            Retour à l'accueil
          </Button>
        </div>
        {/* Le message technique, replie : inutile a l'utilisateur, precieux
            quand il envoie une capture d'ecran pour signaler le probleme. */}
        <details className="w-full">
          <summary className="cursor-pointer font-inter text-xs text-muted-foreground">
            Détail technique
          </summary>
          <pre className="mt-2 overflow-x-auto rounded-md bg-muted p-3 font-mono text-xs">
            {this.state.erreur.message}
          </pre>
        </details>
      </main>
    );
  }
}
