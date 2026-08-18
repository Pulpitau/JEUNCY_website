import { useQuery } from '@tanstack/react-query';
import { UserRole } from '@jeuncy/shared';
import { getMyProfile } from '@/lib/api/candidate-profile';
import { ApiError } from '@/lib/api/client';
import { useAuthStore } from '@/store/auth-store';

// Meme cle que la page Profil : une seule requete pour toute la session, et
// remplir son profil fait disparaitre le bandeau sans rechargement (la page
// Profil invalide deja cette cle apres creation).
export const CANDIDATE_PROFILE_QUERY_KEY = ['candidate-profile'];

interface CandidateProfileStatus {
  // true uniquement quand on SAIT que le profil manque. Reste false pendant le
  // chargement et en cas d'erreur reseau : afficher "ton profil est vide" a un
  // candidat qui a rempli le sien est bien pire que de ne rien afficher.
  isMissing: boolean;
}

// S'inscrire cree une ligne `users` ; le profil candidat n'existe qu'une fois
// le formulaire enregistre (voir CandidateProfileService::createForUser). Un
// candidat sans profil est donc invisible dans la CVtheque — c'est-a-dire
// invisible pour les recruteurs qui paient. Ce hook detecte ce cas pour qu'on
// puisse le lui dire.
export function useCandidateProfileStatus(): CandidateProfileStatus {
  const user = useAuthStore((state) => state.user);
  const isCandidate = user?.role === UserRole.CANDIDATE;

  const query = useQuery({
    queryKey: CANDIDATE_PROFILE_QUERY_KEY,
    queryFn: getMyProfile,
    enabled: isCandidate,
    retry: false,
    staleTime: 5 * 60 * 1000,
  });

  const isMissing =
    isCandidate &&
    query.isError &&
    query.error instanceof ApiError &&
    query.error.code === 'PROFILE_NOT_FOUND';

  return { isMissing };
}
