import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Software } from '@/lib/api/candidate-profile';

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

  function handleAdd() {
    const name = draft.trim();
    if (
      !name ||
      software.some((item) => item.name.toLowerCase() === name.toLowerCase())
    ) {
      setDraft('');
      return;
    }
    void onSync([...software.map((item) => item.name), name]);
    setDraft('');
  }

  function handleRemove(name: string) {
    void onSync(
      software.map((item) => item.name).filter((itemName) => itemName !== name),
    );
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
              handleAdd();
            }
          }}
          disabled={isSubmitting}
        />
        <Button
          type="button"
          variant="outline"
          onClick={handleAdd}
          disabled={isSubmitting}
        >
          Ajouter
        </Button>
      </div>
    </div>
  );
}
