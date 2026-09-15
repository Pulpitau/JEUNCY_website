import { Link } from 'react-router-dom';
import { Sparkles } from 'lucide-react';
import { cn } from '@/lib/utils';

interface FreeForCandidatesBadgeProps {
  className?: string;
}

// Depuis le 2026-09-15, gratuit pour TOUT LE MONDE : le badge ne se contente
// plus de rassurer les candidats, il annonce le modele et renvoie vers la
// page qui l'explique. Le nom du composant est conserve pour ne pas toucher
// ses quatre points d'usage.
export function FreeForCandidatesBadge({ className }: FreeForCandidatesBadgeProps) {
  return (
    <Link
      to="/tarifs"
      className={cn(
        'inline-flex items-center gap-3 rounded-full border border-jeuncy-orange/30 bg-jeuncy-orange/10 px-6 py-3 font-inter text-base font-medium text-foreground transition-colors hover:border-jeuncy-orange/60 hover:bg-jeuncy-orange/20',
        className,
      )}
    >
      <Sparkles className="h-6 w-6 shrink-0 text-jeuncy-orange" aria-hidden="true" />
      <span>
        <span className="font-poppins font-semibold">100 % gratuit</span> pour les
        candidats comme pour les entreprises.
      </span>
    </Link>
  );
}
