import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { Experience, ExperienceInput } from '@/lib/api/candidate-profile';
import { formatMonthYear } from '@/lib/format-date';

const experienceSchema = z.object({
  title: z.string().min(1, 'Le titre est requis.'),
  company: z.string().min(1, "L'entreprise est requise."),
  location: z.string().optional().or(z.literal('')),
  start_date: z.string().min(1, 'La date de début est requise.'),
  end_date: z.string().optional().or(z.literal('')),
  description: z.string().optional().or(z.literal('')),
});

type ExperienceFormValues = z.infer<typeof experienceSchema>;

interface ExperienceSectionProps {
  experiences: Experience[];
  onAdd: (values: ExperienceInput) => Promise<unknown>;
  onDelete: (id: number) => Promise<unknown>;
  isSubmitting: boolean;
}

export function ExperienceSection({
  experiences,
  onAdd,
  onDelete,
  isSubmitting,
}: ExperienceSectionProps) {
  const [showForm, setShowForm] = useState(false);
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<ExperienceFormValues>({ resolver: zodResolver(experienceSchema) });

  async function handleFormSubmit(values: ExperienceFormValues) {
    await onAdd({
      title: values.title,
      company: values.company,
      location: values.location || null,
      start_date: values.start_date,
      end_date: values.end_date || null,
      description: values.description || null,
    });
    reset();
    setShowForm(false);
  }

  return (
    <div className="flex flex-col gap-4">
      {experiences.length === 0 && (
        <p className="font-inter text-sm text-muted-foreground">
          Aucune expérience ajoutée pour l'instant.
        </p>
      )}

      {experiences.map((experience) => (
        <div
          key={experience.id}
          className="flex items-start justify-between gap-4 rounded-md border border-border p-4"
        >
          <div>
            <p className="font-poppins font-medium">{experience.title}</p>
            <p className="text-sm text-muted-foreground">
              {experience.company}
              {experience.location ? ` · ${experience.location}` : ''}
            </p>
            <p className="text-xs text-muted-foreground">
              {formatMonthYear(experience.start_date)} —{' '}
              {experience.end_date ? formatMonthYear(experience.end_date) : "aujourd'hui"}
            </p>
            {experience.description && (
              <p className="mt-2 font-inter text-sm">{experience.description}</p>
            )}
          </div>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => onDelete(experience.id)}
          >
            Supprimer
          </Button>
        </div>
      ))}

      {showForm ? (
        <form
          onSubmit={handleSubmit(handleFormSubmit)}
          noValidate
          className="flex flex-col gap-3 rounded-md border border-border p-4"
        >
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div className="flex flex-col gap-1">
              <Label htmlFor="exp-title">Intitulé</Label>
              <Input
                id="exp-title"
                aria-invalid={!!errors.title}
                {...register('title')}
              />
              {errors.title && (
                <p role="alert" className="text-sm text-destructive">
                  {errors.title.message}
                </p>
              )}
            </div>
            <div className="flex flex-col gap-1">
              <Label htmlFor="exp-company">Entreprise</Label>
              <Input
                id="exp-company"
                aria-invalid={!!errors.company}
                {...register('company')}
              />
              {errors.company && (
                <p role="alert" className="text-sm text-destructive">
                  {errors.company.message}
                </p>
              )}
            </div>
            <div className="flex flex-col gap-1">
              <Label htmlFor="exp-location">Lieu</Label>
              <Input id="exp-location" {...register('location')} />
            </div>
            <div className="flex gap-3">
              <div className="flex flex-1 flex-col gap-1">
                <Label htmlFor="exp-start">Début</Label>
                <Input
                  id="exp-start"
                  type="date"
                  aria-invalid={!!errors.start_date}
                  {...register('start_date')}
                />
                {errors.start_date && (
                  <p role="alert" className="text-sm text-destructive">
                    {errors.start_date.message}
                  </p>
                )}
              </div>
              <div className="flex flex-1 flex-col gap-1">
                <Label htmlFor="exp-end">Fin</Label>
                <Input id="exp-end" type="date" {...register('end_date')} />
              </div>
            </div>
          </div>
          <div className="flex flex-col gap-1">
            <Label htmlFor="exp-description">Description</Label>
            <Textarea id="exp-description" rows={3} {...register('description')} />
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
          + Ajouter une expérience
        </Button>
      )}
    </div>
  );
}
