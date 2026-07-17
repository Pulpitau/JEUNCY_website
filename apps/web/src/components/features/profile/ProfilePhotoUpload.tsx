import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_SIZE_BYTES = 2 * 1024 * 1024;

interface ProfilePhotoUploadProps {
  photoUrl: string | null;
  firstName: string;
  lastName: string;
  onUpload: (file: File) => Promise<unknown>;
  onRemove: () => Promise<unknown>;
  isUploading: boolean;
  isRemoving: boolean;
}

export function ProfilePhotoUpload({
  photoUrl,
  firstName,
  lastName,
  onUpload,
  onRemove,
  isUploading,
  isRemoving,
}: ProfilePhotoUploadProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [error, setError] = useState<string | null>(null);
  const initials = `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase();
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
        aria-label="Changer la photo de profil"
        className={cn(
          'relative h-20 w-20 shrink-0 overflow-hidden rounded-full border-2 border-jeuncy-orange disabled:opacity-50',
          !photoUrl && 'bg-jeuncy-coral',
        )}
      >
        {photoUrl ? (
          <img src={photoUrl} alt="" className="h-full w-full object-cover" />
        ) : (
          <span className="flex h-full w-full items-center justify-center font-poppins text-xl font-bold text-white">
            {initials || '?'}
          </span>
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
            {isUploading ? 'Envoi…' : photoUrl ? 'Changer la photo' : 'Ajouter une photo'}
          </Button>
          {photoUrl && (
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
          JPEG, PNG ou WEBP, 2 Mo max.
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
