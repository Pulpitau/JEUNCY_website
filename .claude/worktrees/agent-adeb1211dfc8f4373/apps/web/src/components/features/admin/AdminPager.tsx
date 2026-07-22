import { Button } from '@/components/ui/button';

interface AdminPagerProps {
  page: number;
  lastPage: number;
  onChange: (page: number) => void;
}

export function AdminPager({ page, lastPage, onChange }: AdminPagerProps) {
  if (lastPage <= 1) return null;

  return (
    <div className="flex items-center justify-center gap-3">
      <Button
        type="button"
        variant="outline"
        size="sm"
        disabled={page <= 1}
        onClick={() => onChange(page - 1)}
      >
        Précédent
      </Button>
      <span className="font-inter text-sm text-muted-foreground">
        Page {page} / {lastPage}
      </span>
      <Button
        type="button"
        variant="outline"
        size="sm"
        disabled={page >= lastPage}
        onClick={() => onChange(page + 1)}
      >
        Suivant
      </Button>
    </div>
  );
}
