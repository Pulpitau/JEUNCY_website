import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Skill } from '@/lib/api/candidate-profile';
import { TAG_MAX_LENGTH, messageFromError, splitTags, tooLongTag } from '@/lib/tag-input';

interface SkillsSectionProps {
  skills: Skill[];
  onSync: (names: string[]) => Promise<unknown>;
  isSubmitting: boolean;
}

export function SkillsSection({ skills, onSync, isSubmitting }: SkillsSectionProps) {
  const [draft, setDraft] = useState('');
  const [error, setError] = useState<string | null>(null);

  async function handleAdd() {
    // Le placeholder invite a separer par des virgules : on tient cette
    // promesse au lieu d'enregistrer la phrase entiere comme une competence.
    const entered = splitTags(draft);
    const known = new Set(skills.map((skill) => skill.name.toLowerCase()));
    const added = entered.filter((name) => !known.has(name.toLowerCase()));

    if (added.length === 0) {
      setDraft('');
      setError(null);

      return;
    }

    const tooLong = tooLongTag(added);
    if (tooLong) {
      setError(
        `« ${tooLong.slice(0, 30)}… » est trop long (${TAG_MAX_LENGTH} caractères maximum). Sépare tes compétences par des virgules.`,
      );

      return;
    }

    try {
      await onSync([...skills.map((skill) => skill.name), ...added]);
      setDraft('');
      setError(null);
    } catch (submitError) {
      // Sans ce message, un refus du serveur se traduisait par « il ne se
      // passe rien » — c'est le bug signale par les etudiants.
      setError(messageFromError(submitError));
    }
  }

  async function handleRemove(name: string) {
    try {
      await onSync(
        skills.map((skill) => skill.name).filter((skillName) => skillName !== name),
      );
      setError(null);
    } catch (submitError) {
      setError(messageFromError(submitError));
    }
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
