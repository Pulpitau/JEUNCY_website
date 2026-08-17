import { useEffect, useRef, type RefObject } from 'react';

export type DismissSource = 'pointer' | 'escape';

interface UseDismissableLayerOptions {
  isOpen: boolean;
  containerRef: RefObject<HTMLElement | null>;
  onDismiss: (source: DismissSource) => void;
}

// Fermeture au clic exterieur / Echap, partagee par tous les panneaux
// flottants de la barre de navigation. Extrait en hook parce que la cloche
// de notifications ne l'avait PAS : son panneau restait ouvert quand on
// cliquait la pastille profil, et comme il est rendu au-dessus (z-index
// superieur), il recouvrait le menu profil et rendait ses entrees
// incliquables entre 768 et 1023 px (constate en mesurant elementFromPoint
// sur chaque entree). Avec ce hook, ouvrir un panneau ferme l'autre : les
// deux ne peuvent plus se superposer.
export function useDismissableLayer({
  isOpen,
  containerRef,
  onDismiss,
}: UseDismissableLayerOptions) {
  // Garde l'effet stable malgre une callback recreee a chaque rendu : sans
  // ca, les listeners seraient detaches/rattaches en boucle.
  const onDismissRef = useRef(onDismiss);
  onDismissRef.current = onDismiss;

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    function handlePointerDown(event: MouseEvent | TouchEvent) {
      if (!containerRef.current?.contains(event.target as Node)) {
        onDismissRef.current('pointer');
      }
    }

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        onDismissRef.current('escape');
      }
    }

    document.addEventListener('mousedown', handlePointerDown);
    document.addEventListener('touchstart', handlePointerDown);
    document.addEventListener('keydown', handleKeyDown);

    return () => {
      document.removeEventListener('mousedown', handlePointerDown);
      document.removeEventListener('touchstart', handlePointerDown);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [isOpen, containerRef]);
}
