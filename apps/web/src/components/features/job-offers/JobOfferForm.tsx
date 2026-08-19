import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { ContractType, WorkMode } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { WORK_MODE_LABELS } from '@/lib/work-mode-labels';
import { COMPENSATION_PERIOD_OPTIONS } from '@/lib/format-compensation';
import type { JobOffer, JobOfferInput } from '@/lib/api/job-offers';

export type JobOfferFormVariant = 'COMPANY' | 'CFA';

const CONTRACT_TYPE_LABELS: Record<string, string> = {
  [ContractType.ALTERNANCE]: 'Alternance',
  [ContractType.SAISONNIER]: 'Saisonnier',
  [ContractType.BENEVOLAT]: 'Bénévolat',
  [ContractType.JOB_ETUDIANT]: 'Job étudiant',
  [ContractType.STAGE]: 'Stage',
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
    ContractType.JOB_ETUDIANT,
    ContractType.STAGE,
  ]),
  city: z.string().optional().or(z.literal('')),
  work_mode: z.union([z.nativeEnum(WorkMode), z.literal('')]).optional(),
  // Saisi en texte (un <input type="number"> renvoie une chaine) puis
  // converti a la soumission. Le regex refuse tout ce qui n'est pas une
  // suite de chiffres : « 1 200 » ou « 1200€ » partiraient sinon en base
  // comme montant illisible.
  compensation_amount: z
    .string()
    .regex(/^\d*$/, 'Indique uniquement un montant en chiffres, sans espace ni symbole.')
    .optional()
    .or(z.literal('')),
  compensation_period: z
    .union([z.enum(['HOURLY', 'MONTHLY', 'YEARLY']), z.literal('')])
    .optional(),
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
      work_mode: offer?.work_mode ?? '',
      compensation_amount: offer?.compensation_amount
        ? String(offer.compensation_amount)
        : '',
      // Mensuel par defaut : c'est la periode de loin la plus courante pour
      // une alternance, autant epargner un clic dans le cas general.
      compensation_period: offer?.compensation_period ?? 'MONTHLY',
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
      work_mode: values.work_mode || null,
      compensation_amount: values.compensation_amount
        ? Number(values.compensation_amount)
        : null,
      // Pas de periode sans montant : « / mois » seul ne veut rien dire, et
      // laisserait une donnee incoherente en base.
      compensation_period: values.compensation_amount
        ? values.compensation_period || 'MONTHLY'
        : null,
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
          <Label htmlFor="offer-work-mode">
            Type d'offre (présentiel, hybride, distanciel)
          </Label>
          <select
            id="offer-work-mode"
            className={selectClassName}
            {...register('work_mode')}
          >
            <option value="">Non précisé</option>
            {Object.entries(WORK_MODE_LABELS).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </div>
        {/* Montant et periode separes plutot qu'un texte libre : le candidat
            lisait « 1200 » sans savoir s'il s'agissait d'un mensuel, d'un
            annuel ou d'un horaire. L'affichage est fabrique par
            formatCompensation, identique sur toutes les offres. */}
        <div className="flex flex-col gap-2">
          <Label htmlFor="offer-compensation-amount">Rémunération brute</Label>
          <div className="flex gap-2">
            <div className="relative flex-1">
              <Input
                id="offer-compensation-amount"
                inputMode="numeric"
                placeholder="Ex : 1200"
                aria-invalid={!!errors.compensation_amount}
                className="pr-8"
                {...register('compensation_amount')}
              />
              <span
                className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 font-inter text-sm text-muted-foreground"
                aria-hidden="true"
              >
                €
              </span>
            </div>
            <select
              id="offer-compensation-period"
              aria-label="Périodicité de la rémunération"
              className="h-10 rounded-md border border-input bg-background px-3 font-inter text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              {...register('compensation_period')}
            >
              {COMPENSATION_PERIOD_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>
          {errors.compensation_amount ? (
            <p role="alert" className="font-inter text-sm text-destructive">
              {errors.compensation_amount.message}
            </p>
          ) : (
            <p className="font-inter text-xs text-muted-foreground">
              Laisse vide si la rémunération n'est pas définie (bénévolat, à négocier…).
            </p>
          )}
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

      {variant === 'COMPANY' && (
        <div className="flex flex-col gap-2">
          <Label htmlFor="offer-benefits">Avantages (facultatif)</Label>
          <Textarea
            id="offer-benefits"
            rows={3}
            placeholder="Ex : Tickets restaurant, mutuelle, télétravail 2j/semaine, prime de fin d'année…"
            {...register('benefits')}
          />
        </div>
      )}

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
