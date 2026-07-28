import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { ContractType } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { JobOffer, JobOfferInput } from '@/lib/api/job-offers';

export type JobOfferFormVariant = 'COMPANY' | 'CFA';

const CONTRACT_TYPE_LABELS: Record<string, string> = {
  [ContractType.ALTERNANCE]: 'Alternance',
  [ContractType.SAISONNIER]: 'Saisonnier',
  [ContractType.BENEVOLAT]: 'Bénévolat',
};

const EXPERIENCE_LEVEL_OPTIONS = [
  'Débutant accepté',
  '1 à 2 ans',
  '3 à 5 ans',
  '5 ans et plus',
];

const DIPLOMA_LEVEL_OPTIONS = [
  'CAP / BEP',
  'Bac',
  'Bac+2 (BTS, DUT)',
  'Bac+3 (Licence, Bachelor)',
  'Bac+5 (Master, Ingénieur)',
];

const jobOfferSchema = z.object({
  title: z.string().min(1, "L'intitulé est requis."),
  description: z.string().min(1, 'La description est requise.'),
  contract_type: z.enum([
    ContractType.ALTERNANCE,
    ContractType.SAISONNIER,
    ContractType.BENEVOLAT,
  ]),
  city: z.string().optional().or(z.literal('')),
  compensation: z.string().optional().or(z.literal('')),
  experience_level: z.string().optional().or(z.literal('')),
  benefits: z.string().optional().or(z.literal('')),
  diploma_level: z.string().optional().or(z.literal('')),
  training_rhythm: z.string().optional().or(z.literal('')),
});

type JobOfferFormValues = z.infer<typeof jobOfferSchema>;

interface JobOfferFormProps {
  variant: JobOfferFormVariant;
  offer?: JobOffer;
  onSubmit: (values: JobOfferInput) => Promise<unknown>;
  onCancel?: () => void;
  isSubmitting: boolean;
  submitError?: string | null;
}

const selectClassName = cn(
  'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm font-inter focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
);

export function JobOfferForm({
  variant,
  offer,
  onSubmit,
  onCancel,
  isSubmitting,
  submitError,
}: JobOfferFormProps) {
  const [skills, setSkills] = useState<string[]>(
    offer?.skills.map((skill) => skill.name) ?? [],
  );
  const [skillDraft, setSkillDraft] = useState('');

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<JobOfferFormValues>({
    resolver: zodResolver(jobOfferSchema),
    defaultValues: {
      title: offer?.title ?? '',
      description: offer?.description ?? '',
      contract_type: offer?.contract_type ?? ContractType.ALTERNANCE,
      city: offer?.city ?? '',
      compensation: offer?.compensation ?? '',
      experience_level: offer?.experience_level ?? '',
      benefits: offer?.benefits ?? '',
      diploma_level: offer?.diploma_level ?? '',
      training_rhythm: offer?.training_rhythm ?? '',
    },
  });

  function addSkill() {
    const name = skillDraft.trim();
    if (!name || skills.some((skill) => skill.toLowerCase() === name.toLowerCase())) {
      setSkillDraft('');
      return;
    }
    setSkills([...skills, name]);
    setSkillDraft('');
  }

  function removeSkill(name: string) {
    setSkills(skills.filter((skill) => skill !== name));
  }

  async function handleFormSubmit(values: JobOfferFormValues) {
    await onSubmit({
      title: values.title,
      description: values.description,
      contract_type: values.contract_type,
      city: values.city || null,
      compensation: values.compensation || null,
      experience_level: variant === 'COMPANY' ? values.experience_level || null : null,
      benefits: variant === 'COMPANY' ? values.benefits || null : null,
      diploma_level: variant === 'CFA' ? values.diploma_level || null : null,
      training_rhythm: variant === 'CFA' ? values.training_rhythm || null : null,
      skills,
    });
  }

  const skillsLabel =
    variant === 'CFA' ? 'Compétences et expériences acquises' : 'Compétences recherchées';
  const skillsPlaceholder =
    variant === 'CFA'
      ? 'Ex : Gestion de projet, anglais professionnel, prise de parole…'
      : 'Ex : React, Vente, Relation client…';

  return (
    <form
      onSubmit={handleSubmit(handleFormSubmit)}
      noValidate
      className="flex flex-col gap-4 rounded-md border border-border p-4"
    >
      {submitError && (
        <p role="alert" className="font-inter text-sm text-destructive">
          {submitError}
        </p>
      )}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className="flex flex-col gap-2">
          <Label htmlFor="offer-title">
            {variant === 'CFA' ? 'Intitulé de la formation' : 'Intitulé du poste'}
          </Label>
          <Input id="offer-title" aria-invalid={!!errors.title} {...register('title')} />
          {errors.title && (
            <p role="alert" className="text-sm text-destructive">
              {errors.title.message}
            </p>
          )}
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="offer-contract-type">Type de contrat</Label>
          <select
            id="offer-contract-type"
            className={selectClassName}
            {...register('contract_type')}
          >
            {Object.entries(CONTRACT_TYPE_LABELS).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="offer-city">Ville</Label>
          <Input id="offer-city" {...register('city')} />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="offer-compensation">Rémunération</Label>
          <Input
            id="offer-compensation"
            placeholder="Ex : 800 € / mois, Selon profil, SMIC…"
            {...register('compensation')}
          />
        </div>

        {variant === 'COMPANY' && (
          <div className="flex flex-col gap-2">
            <Label htmlFor="offer-experience-level">Niveau d'expérience requis</Label>
            <select
              id="offer-experience-level"
              className={selectClassName}
              {...register('experience_level')}
            >
              <option value="">Non précisé</option>
              {EXPERIENCE_LEVEL_OPTIONS.map((option) => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
          </div>
        )}

        {variant === 'CFA' && (
          <div className="flex flex-col gap-2">
            <Label htmlFor="offer-diploma-level">Niveau du diplôme visé</Label>
            <select
              id="offer-diploma-level"
              className={selectClassName}
              {...register('diploma_level')}
            >
              <option value="">Non précisé</option>
              {DIPLOMA_LEVEL_OPTIONS.map((option) => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
          </div>
        )}

        {variant === 'CFA' && (
          <div className="flex flex-col gap-2">
            <Label htmlFor="offer-training-rhythm">Rythme de l'alternance</Label>
            <Input
              id="offer-training-rhythm"
              placeholder="Ex : 2 jours en centre / 3 jours en entreprise"
              {...register('training_rhythm')}
            />
          </div>
        )}
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="skill-draft">{skillsLabel}</Label>
        <div className="flex flex-wrap gap-2">
          {skills.length === 0 && (
            <p className="font-inter text-sm text-muted-foreground">
              Aucune compétence ajoutée pour l'instant.
            </p>
          )}
          {skills.map((skill) => (
            <Badge key={skill} variant="secondary" className="gap-1">
              {skill}
              <button
                type="button"
                onClick={() => removeSkill(skill)}
                aria-label={`Retirer ${skill}`}
                className="ml-1 text-muted-foreground hover:text-destructive"
              >
                ×
              </button>
            </Badge>
          ))}
        </div>
        <div className="flex gap-2">
          <Input
            id="skill-draft"
            placeholder={skillsPlaceholder}
            value={skillDraft}
            onChange={(event) => setSkillDraft(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === 'Enter') {
                event.preventDefault();
                addSkill();
              }
            }}
          />
          <Button type="button" variant="outline" onClick={addSkill}>
            Ajouter
          </Button>
        </div>
      </div>

      {variant === 'COMPANY' && (
        <div className="flex flex-col gap-2">
          <Label htmlFor="offer-benefits">Avantages</Label>
          <Textarea
            id="offer-benefits"
            rows={3}
            placeholder="Ex : Tickets restaurant, mutuelle, télétravail 2j/semaine, prime de fin d'année…"
            {...register('benefits')}
          />
        </div>
      )}

      <div className="flex flex-col gap-2">
        <Label htmlFor="offer-description">Description</Label>
        <Textarea
          id="offer-description"
          rows={5}
          aria-invalid={!!errors.description}
          {...register('description')}
        />
        {errors.description && (
          <p role="alert" className="text-sm text-destructive">
            {errors.description.message}
          </p>
        )}
      </div>

      <div className="flex gap-2">
        <Button type="submit" variant="gradient" disabled={isSubmitting}>
          {isSubmitting ? 'Enregistrement…' : offer ? 'Mettre à jour' : "Créer l'offre"}
        </Button>
        {onCancel && (
          <Button type="button" variant="outline" onClick={onCancel}>
            Annuler
          </Button>
        )}
      </div>
    </form>
  );
}
