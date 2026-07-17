import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Skill } from '@/lib/api/candidate-profile';

interface SkillsSectionProps {
  skills: Skill[];
  onSync: (names: string[]) => Promise<unknown>;
  isSubmitting: boolean;
}

export function SkillsSection({ skills, onSync, isSubmitting }: SkillsSectionProps) {
  const [draft, setDraft] = useState('');

  function handleAdd() {
    const name = draft.trim();
    if (
      !name ||
      skills.some((skill) => skill.name.toLowerCase() === name.toLowerCase())
    ) {
      setDraft('');
      return;
    }
    void onSync([...skills.map((skill) => skill.name), name]);
    setDraft('');
  }

  function handleRemove(name: string) {
    void onSync(
      skills.map((skill) => skill.name).filter((skillName) => skillName !== name),
    );
  }

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap gap-2">
        {skills.length === 0 && (
          <p className="font-inter text-sm text-muted-foreground">
            Aucune compétence ajoutée pour l'instant.
          </p>
        )}
        {skills.map((skill) => (
          <Badge key={skill.id} variant="secondary" className="gap-1">
            {skill.name}
            <button
              type="button"
              onClick={() => handleRemove(skill.name)}
              aria-label={`Retirer ${skill.name}`}
              className="ml-1 text-muted-foreground hover:text-destructive"
            >
              ×
            </button>
          </Badge>
        ))}
      </div>
      <div className="flex gap-2">
        <Label htmlFor="skill-input" className="sr-only">
          Ajouter une compétence
        </Label>
        <Input
          id="skill-input"
          placeholder="Ex : React, Vente, Relation client…"
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
