import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Language, LanguageInput } from '@/lib/api/candidate-profile';

const languageSchema = z.object({
  name: z.string().min(1, 'La langue est requise.'),
  level: z.string().min(1, 'Le niveau est requis.'),
});

type LanguageFormValues = z.infer<typeof languageSchema>;

interface LanguagesSectionProps {
  languages: Language[];
  onAdd: (values: LanguageInput) => Promise<unknown>;
  onDelete: (id: number) => Promise<unknown>;
  isSubmitting: boolean;
}

export function LanguagesSection({
  languages,
  onAdd,
  onDelete,
  isSubmitting,
}: LanguagesSectionProps) {
  const [showForm, setShowForm] = useState(false);
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<LanguageFormValues>({ resolver: zodResolver(languageSchema) });

  async function handleFormSubmit(values: LanguageFormValues) {
    await onAdd({ name: values.name, level: values.level });
    reset();
    setShowForm(false);
  }

  return (
    <div className="flex flex-col gap-4">
      {languages.length === 0 && (
        <p className="font-inter text-sm text-muted-foreground">
          Aucune langue ajoutée pour l'instant.
        </p>
      )}

      {languages.length > 0 && (
        <ul className="flex flex-col gap-2">
          {languages.map((language) => (
            <li
              key={language.id}
              className="flex items-center justify-between gap-4 rounded-md border border-border p-3"
            >
              <p className="font-inter text-sm">
                <span className="font-medium">{language.name}</span>
                <span className="text-muted-foreground"> — {language.level}</span>
              </p>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => onDelete(language.id)}
              >
                Supprimer
              </Button>
            </li>
          ))}
        </ul>
      )}

      {showForm ? (
        <form
          onSubmit={handleSubmit(handleFormSubmit)}
          noValidate
          className="flex flex-col gap-3 rounded-md border border-border p-4"
        >
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div className="flex flex-col gap-1">
              <Label htmlFor="lang-name">Langue</Label>
              <Input
                id="lang-name"
                placeholder="Ex : Anglais"
                aria-invalid={!!errors.name}
                {...register('name')}
              />
              {errors.name && (
                <p role="alert" className="text-sm text-destructive">
                  {errors.name.message}
                </p>
              )}
            </div>
            <div className="flex flex-col gap-1">
              <Label htmlFor="lang-level">Niveau</Label>
              <Input
                id="lang-level"
                placeholder="Ex : B2, courant, natif"
                aria-invalid={!!errors.level}
                {...register('level')}
              />
              {errors.level && (
                <p role="alert" className="text-sm text-destructive">
                  {errors.level.message}
                </p>
              )}
            </div>
          </div>
          <div className="flex gap-2">
            <Button type="submit" variant="gradient" size="sm" disabled={isSubmitting}>
              {isSubmitting ? 'Ajout…' : 'Ajouter'}
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => setShowForm(false)}
            >
              Annuler
            </Button>
          </div>
        </form>
      ) : (
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="self-start"
          onClick={() => setShowForm(true)}
        >
          + Ajouter une langue
        </Button>
      )}
    </div>
  );
}
