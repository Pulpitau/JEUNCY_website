import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Education, EducationInput } from '@/lib/api/candidate-profile';
import { formatMonthYear } from '@/lib/format-date';
import { messageFromError } from '@/lib/tag-input';

const educationSchema = z.object({
  degree: z.string().min(1, 'Le diplôme est requis.'),
  school: z.string().min(1, "L'établissement est requis."),
  field_of_study: z.string().optional().or(z.literal('')),
  start_date: z.string().min(1, 'La date de début est requise.'),
  end_date: z.string().optional().or(z.literal('')),
});

type EducationFormValues = z.infer<typeof educationSchema>;

const EMPTY: EducationFormValues = {
  degree: '',
  school: '',
  field_of_study: '',
  start_date: '',
  end_date: '',
};

// Voir ExperienceSection : les dates arrivent en ISO complet, l'input les
// veut en AAAA-MM-JJ.
function formValuesFrom(education: Education): EducationFormValues {
  return {
    degree: education.degree,
    school: education.school,
    field_of_study: education.field_of_study ?? '',
    start_date: education.start_date?.slice(0, 10) ?? '',
    end_date: education.end_date?.slice(0, 10) ?? '',
  };
}

interface EducationSectionProps {
  educations: Education[];
  onAdd: (values: EducationInput) => Promise<unknown>;
  // Demande d'un etudiant (2026-09-23) : corriger une formation deja saisie.
  onUpdate: (id: number, values: EducationInput) => Promise<unknown>;
  onDelete: (id: number) => Promise<unknown>;
  isSubmitting: boolean;
}

export function EducationSection({
  educations,
  onAdd,
  onUpdate,
  onDelete,
  isSubmitting,
}: EducationSectionProps) {
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<EducationFormValues>({ resolver: zodResolver(educationSchema) });

  function openForAdd() {
    reset(EMPTY);
    setEditingId(null);
    setError(null);
    setShowForm(true);
  }

  function openForEdit(education: Education) {
    reset(formValuesFrom(education));
    setEditingId(education.id);
    setError(null);
    setShowForm(true);
  }

  function closeForm() {
    reset(EMPTY);
    setEditingId(null);
    setError(null);
    setShowForm(false);
  }

  async function handleFormSubmit(values: EducationFormValues) {
    const payload: EducationInput = {
      degree: values.degree,
      school: values.school,
      field_of_study: values.field_of_study || null,
      start_date: values.start_date,
      end_date: values.end_date || null,
    };

    // Voir ExperienceSection : un echec d'enregistrement doit se voir.
    try {
      if (editingId !== null) {
        await onUpdate(editingId, payload);
      } else {
        await onAdd(payload);
      }
      closeForm();
    } catch (submitError) {
      setError(messageFromError(submitError));
    }
  }

  return (
    <div className="flex flex-col gap-4">
      {educations.length === 0 && (
        <p className="font-inter text-sm text-muted-foreground">
          Aucune formation ajoutée pour l'instant.
        </p>
      )}

      {educations.map((education) => (
        <div
          key={education.id}
          className="flex items-start justify-between gap-4 rounded-md border border-border p-4"
        >
          <div>
            <p className="font-poppins font-medium">{education.degree}</p>
            <p className="text-sm text-muted-foreground">
              {education.school}
              {education.field_of_study ? ` · ${education.field_of_study}` : ''}
            </p>
            <p className="text-xs text-muted-foreground">
              {formatMonthYear(education.start_date)} —{' '}
              {education.end_date ? formatMonthYear(education.end_date) : 'en cours'}
            </p>
          </div>
          <div className="flex shrink-0 gap-1">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => openForEdit(education)}
            >
              Modifier
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => onDelete(education.id)}
            >
              Supprimer
            </Button>
          </div>
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
              <Label htmlFor="edu-degree">Diplôme</Label>
              <Input
                id="edu-degree"
                aria-invalid={!!errors.degree}
                {...register('degree')}
              />
              {errors.degree && (
                <p role="alert" className="text-sm text-destructive">
                  {errors.degree.message}
                </p>
              )}
            </div>
            <div className="flex flex-col gap-1">
              <Label htmlFor="edu-school">Établissement</Label>
              <Input
                id="edu-school"
                aria-invalid={!!errors.school}
                {...register('school')}
              />
              {errors.school && (
                <p role="alert" className="text-sm text-destructive">
                  {errors.school.message}
                </p>
              )}
            </div>
            <div className="flex flex-col gap-1">
              <Label htmlFor="edu-field">Filière</Label>
              <Input id="edu-field" {...register('field_of_study')} />
            </div>
            <div className="flex gap-3">
              <div className="flex flex-1 flex-col gap-1">
                <Label htmlFor="edu-start">Début</Label>
                <Input
                  id="edu-start"
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
                <Label htmlFor="edu-end">Fin</Label>
                <Input id="edu-end" type="date" {...register('end_date')} />
              </div>
            </div>
          </div>
          <div className="flex gap-2">
            <Button type="submit" variant="gradient" size="sm" disabled={isSubmitting}>
              {isSubmitting
                ? 'Enregistrement…'
                : editingId !== null
                  ? 'Enregistrer'
                  : 'Ajouter'}
            </Button>
            <Button type="button" variant="outline" size="sm" onClick={closeForm}>
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
          onClick={openForAdd}
        >
          + Ajouter une formation
        </Button>
      )}

      {error && (
        <p role="alert" className="font-inter text-sm text-destructive">
          {error}
        </p>
      )}
    </div>
  );
}
