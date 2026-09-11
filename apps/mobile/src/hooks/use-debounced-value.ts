import { useEffect, useState } from 'react';

// Renvoie la valeur une fois qu'elle a cesse de changer pendant `delayMs`.
// Sert a la recherche : appeler l'API a chaque lettre tapee ferait une requete
// par frappe, dont la plupart seraient annulees avant d'avoir servi.
export function useDebouncedValue<T>(value: T, delayMs = 400): T {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delayMs);

    return () => clearTimeout(timer);
  }, [value, delayMs]);

  return debounced;
}
