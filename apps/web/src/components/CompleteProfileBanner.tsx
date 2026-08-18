import { Link, useLocation } from 'react-router-dom';
import { ArrowRight, Sparkles } from 'lucide-react';
import { useCandidateProfileStatus } from '@/hooks/use-candidate-profile-status';

// Bandeau affiche a un candidat inscrit qui n'a pas encore enregistre son
// profil.
//
// Raison d'etre : postuler exige deja un profil (ApplicationService le refuse
// sinon), donc le candidat qui postule est force de le remplir. Le trou
// concerne ceux qui s'inscrivent SANS postuler — precisement la promesse
// vendue aux recruteurs ("les candidats vous trouvent sans postuler") et la
// population que la CVtheque monetise. Sans profil, ils y sont invisibles.
//
// Volontairement non bloquant : un candidat a le droit de consulter les offres
// avant de se decrire. On informe, on ne barre pas la route. Masque sur
// /profile, ou il serait redondant avec le formulaire juste en dessous.
export function CompleteProfileBanner() {
  const { isMissing } = useCandidateProfileStatus();
  const { pathname } = useLocation();

  if (!isMissing || pathname === '/profile') {
    return null;
  }

  return (
    <div className="border-b border-jeuncy-orange/30 bg-jeuncy-orange/10">
      <div className="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <p className="flex items-start gap-2 font-inter text-sm text-foreground">
          <Sparkles
            className="mt-0.5 h-4 w-4 shrink-0 text-jeuncy-orange"
            aria-hidden="true"
          />
          <span>
            <span className="font-semibold">Ton profil est encore vide.</span> Complète-le
            pour que les entreprises et les CFA puissent te trouver, sans même que tu aies
            à postuler.
          </span>
        </p>

        <Link
          to="/profile"
          className="group inline-flex min-h-[44px] shrink-0 items-center justify-center gap-2 rounded-full bg-jeuncy-gradient px-5 font-poppins text-sm font-semibold text-white shadow-sm transition-all hover:shadow-md"
        >
          Compléter mon profil
          <ArrowRight
            className="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1"
            aria-hidden="true"
          />
        </Link>
      </div>
    </div>
  );
}
