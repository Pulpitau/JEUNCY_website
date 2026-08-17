import { useRef, useState } from 'react';
import { Building2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_SIZE_BYTES = 2 * 1024 * 1024;

interface LogoUploadProps {
  logoUrl: string | null;
  fallbackIcon?: React.ComponentType<{ className?: string }>;
  onUpload: (file: File) => Promise<unknown>;
  onRemove: () => Promise<unknown>;
  isUploading: boolean;
  isRemoving: boolean;
}

export function LogoUpload({
  logoUrl,
  fallbackIcon: FallbackIcon = Building2,
  onUpload,
  onRemove,
  isUploading,
  isRemoving,
}: LogoUploadProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [error, setError] = useState<string | null>(null);
  const isBusy = isUploading || isRemoving;

  function handleFileChange(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;

    if (!ACCEPTED_TYPES.includes(file.type)) {
      setError('Formats acceptés : JPEG, PNG, WEBP.');
      return;
    }
    if (file.size > MAX_SIZE_BYTES) {
      setError("L'image ne doit pas dépasser 2 Mo.");
      return;
    }

    setError(null);
    void onUpload(file);
  }

  return (
    <div className="flex items-center gap-4">
      <button
        type="button"
        onClick={() => inputRef.current?.click()}
        disabled={isBusy}
        aria-label="Changer le logo"
        className={cn(
          'relative flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border-2 border-jeuncy-orange bg-muted disabled:opacity-50',
        )}
      >
        {logoUrl ? (
          <img src={logoUrl} alt="" className="h-full w-full object-contain p-1" />
        ) : (
          <FallbackIcon className="h-8 w-8 text-muted-foreground" />
        )}
      </button>

      <div className="flex flex-col gap-2">
        <div className="flex gap-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => inputRef.current?.click()}
            disabled={isBusy}
          >
            {isUploading ? 'Envoi…' : logoUrl ? 'Changer le logo' : 'Ajouter un logo'}
          </Button>
          {logoUrl && (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => void onRemove()}
              disabled={isBusy}
            >
              {isRemoving ? 'Suppression…' : 'Supprimer'}
            </Button>
          )}
        </div>
        <p className="font-inter text-xs text-muted-foreground">
          JPEG, PNG ou WEBP, 2 Mo max. Visible sur tes offres publiées.
        </p>
        {error && (
          <p role="alert" className="text-xs text-destructive">
            {error}
          </p>
        )}
      </div>

      <input
        ref={inputRef}
        type="file"
        accept={ACCEPTED_TYPES.join(',')}
        onChange={handleFileChange}
        className="hidden"
      />
    </div>
  );
}
