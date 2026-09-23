import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Software } from '@/lib/api/candidate-profile';
import { TAG_MAX_LENGTH, messageFromError, splitTags, tooLongTag } from '@/lib/tag-input';

interface SoftwareSectionProps {
  software: Software[];
  onSync: (names: string[]) => Promise<unknown>;
  isSubmitting: boolean;
}

export function SoftwareSection({
  software,
  onSync,
  isSubmitting,
}: SoftwareSectionProps) {
  const [draft, setDraft] = useState('');
  const [error, setError] = useState<string | null>(null);

  // Meme correction que pour les competences : le placeholder promet une
  // liste separee par des virgules, et un refus du serveur doit se voir.
  async function handleAdd() {
    const entered = splitTags(draft);
    const known = new Set(software.map((item) => item.name.toLowerCase()));
    const added = entered.filter((name) => !known.has(name.toLowerCase()));

    if (added.length === 0) {
      setDraft('');
      setError(null);

      return;
    }

    const tooLong = tooLongTag(added);
    if (tooLong) {
      setError(
        `« ${tooLong.slice(0, 30)}… » est trop long (${TAG_MAX_LENGTH} caractères maximum). Sépare tes logiciels par des virgules.`,
      );

      return;
    }

    try {
      await onSync([...software.map((item) => item.name), ...added]);
      setDraft('');
      setError(null);
    } catch (submitError) {
      setError(messageFromError(submitError));
    }
  }

  async function handleRemove(name: string) {
    try {
      await onSync(
        software.map((item) => item.name).filter((itemName) => itemName !== name),
      );
      setError(null);
    } catch (submitError) {
      setError(messageFromError(submitError));
    }
  }

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap gap-2">
        {software.length === 0 && (
          <p className="font-inter text-sm text-muted-foreground">
            Aucun logiciel ajouté pour l'instant.
          </p>
        )}
        {software.map((item) => (
          <Badge key={item.id} variant="secondary" className="gap-1">
            {item.name}
            <button
              type="button"
              onClick={() => handleRemove(item.name)}
              aria-label={`Retirer ${item.name}`}
              className="ml-1 text-muted-foreground hover:text-destructive"
            >
              ×
            </button>
          </Badge>
        ))}
      </div>
      <div className="flex gap-2">
        <Label htmlFor="software-input" className="sr-only">
          Ajouter un logiciel
        </Label>
        <Input
          id="software-input"
          placeholder="Ex : WordPress, Excel, Photoshop…"
          value={draft}
          onChange={(event) => setDraft(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === 'Enter') {
              event.preventDefault();
              void handleAdd();
            }
          }}
          disabled={isSubmitting}
        />
        <Button
          type="button"
          variant="outline"
          onClick={() => void handleAdd()}
          disabled={isSubmitting}
        >
          Ajouter
        </Button>
      </div>
      {error && (
        <p role="alert" className="font-inter text-sm text-destructive">
          {error}
        </p>
      )}
    </div>
  );
}
