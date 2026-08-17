import { Sparkles } from 'lucide-react';
import { cn } from '@/lib/utils';

interface FreeForCandidatesBadgeProps {
  className?: string;
}

export function FreeForCandidatesBadge({ className }: FreeForCandidatesBadgeProps) {
  return (
    <div
      className={cn(
        'inline-flex items-center gap-3 rounded-full border border-jeuncy-orange/30 bg-jeuncy-orange/10 px-6 py-3 font-inter text-base font-medium text-foreground',
        className,
      )}
    >
      <Sparkles className="h-6 w-6 shrink-0 text-jeuncy-orange" aria-hidden="true" />
      <span>
        <span className="font-poppins font-semibold">100% gratuit</span> pour les
        candidats — seules les entreprises et les CFA paient pour publier.
      </span>
    </div>
  );
}
