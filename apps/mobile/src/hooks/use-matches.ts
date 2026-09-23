import { useQuery } from '@tanstack/react-query';

import {
  getMatch,
  listMatches,
  type CandidateMatch,
  type EmployerMatch,
  type Match,
} from '@/lib/api/matches';

// Les matchs de l'appelant. Une seule route pour les deux roles : c'est le
// serveur qui choisit la forme de la reponse, jamais un parametre du client.
//
// Le type attendu est passe par l'appelant (`useMatches<EmployerMatch>()`)
// parce que l'ecran, lui, sait de quel cote il est monte. La verification a
// l'execution reste `isEmployerMatch` sur la donnee recue.

export const MATCHES_KEY = ['matches'] as const;

export function useMatches<T extends Match = Match>() {
  return useQuery({
    queryKey: MATCHES_KEY,
    queryFn: () => listMatches<T>(),
  });
}

export function useMatch<T extends Match = Match>(id: number) {
  return useQuery({
    queryKey: [...MATCHES_KEY, id],
    queryFn: () => getMatch<T>(id),
  });
}

export type { CandidateMatch, EmployerMatch, Match };
