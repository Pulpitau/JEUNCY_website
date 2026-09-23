import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { ContractType, OfferSector, WorkMode } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { WORK_MODE_LABELS } from '@/lib/work-mode-labels';
import { COMPENSATION_PERIOD_OPTIONS } from '@/lib/format-compensation';
import {
  OFFER_SECTOR_LABELS,
  RECRUITMENT_RADIUS_OPTIONS,
} from '@/lib/offer-sector-labels';
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
  // Les bornes reproduisent celles du serveur (min:1, max:999999) : sans
  // elles, « 0 » ou un montant a sept chiffres passaient la validation client
  // puis revenaient en 400, avec un message d'erreur generique que
  // l'entreprise ne pouvait rattacher a aucun champ.
  compensation_amount: z
    .string()
    .regex(/^\d*$/, 'Indique uniquement un montant en chiffres, sans espace ni symbole.')
    .refine(
      (value) => value === '' || Number(value) >= 1,
      'Indique un montant supérieur à 0.',
    )
    .refine(
      (value) => value === '' || Number(value) <= 999999,
      'Ce montant dépasse la limite autorisée.',
    )
    .optional()
    .or(z.literal('')),
  compensation_period: z
    .union([z.enum(['HOURLY', 'MONTHLY', 'YEARLY']), z.literal('')])
    .optional(),
  experience_level: z.string().optional().or(z.literal('')),
  benefits: z.string().optional().or(z.literal('')),
  diploma_level: z.string().optional().or(z.literal('')),
  training_rhythm: z.string().optional().or(z.literal('')),
  // Champs du modele match (lot 1). Le code postal est le seul qui change
  // quelque chose de visible tout de suite : sans lui, l'offre n'est
  // geocodee nulle part, donc elle n'entre dans aucune pile « Découvrir »
  // — ni celle du candidat, ni celle de l'entreprise.
  postal_code: z
    .string()
    .regex(/^(\d{5})?$/, 'Un code postal à 5 chiffres, ou rien.')
    .optional()
    .or(z.literal('')),
  sector: z.union([z.nativeEnum(OfferSector), z.literal('')]).optional(),
  recruitment_radius_km: z.string().optional().or(z.literal('')),
  schedule: z.string().max(255, '255 caractères maximum.').optional().or(z.literal('')),
  start_date: z.string().optional().or(z.literal('')),
  // Le serveur borne a 16-18 : en dessous de 16 l'application refuse de
  // toute facon le parcours match, au-dela de 18 ce n'est plus un age
  // minimum legal mais une preference, qui n'a pas sa place ici.
  minimum_age: z
    .string()
    .refine(
      (value) => value === '' || (Number(value) >= 16 && Number(value) <= 18),
      'Entre 16 et 18 ans, ou rien.',
    )
    .optional()
    .or(z.literal('')),
  requires_driving_license: z.boolean().optional(),
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
      postal_code: offer?.postal_code ?? '',
      sector: offer?.sector ?? '',
      // 30 km par defaut, comme la colonne en base : un rayon vide partirait
      // en null et le serveur refuserait (la colonne a un defaut et n'accepte
      // pas NULL).
      recruitment_radius_km: String(offer?.recruitment_radius_km ?? 30),
      schedule: offer?.schedule ?? '',
      // Le serveur renvoie une date ISO complete ; <input type="date"> veut
      // exactement AAAA-MM-JJ, sans quoi le champ s'affiche vide.
      start_date: offer?.start_date?.slice(0, 10) ?? '',
      minimum_age: offer?.minimum_age ? String(offer.minimum_age) : '',
      requires_driving_license: offer?.requires_driving_license ?? false,
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
      // Number() avant le test, et non la chaine : « 0 » est une chaine
      // truthy en JS, donc le garde « vide -> null » le laissait passer et le
      // serveur le refusait ensuite (min:1).
      compensation_amount: Number(values.compensation_amount) || null,
      // Pas de periode sans montant : « / mois » seul ne veut rien dire, et
      // laisserait une donnee incoherente en base.
      compensation_period: Number(values.compensation_amount)
        ? values.compensation_period || 'MONTHLY'
        : null,
      experience_level: variant === 'COMPANY' ? values.experience_level || null : null,
      benefits: variant === 'COMPANY' ? values.benefits || null : null,
      diploma_level: variant === 'CFA' ? values.diploma_level || null : null,
      training_rhythm: variant === 'CFA' ? values.training_rhythm || null : null,
      postal_code: values.postal_code || null,
      sector: values.sector || null,
      // Toujours envoye : la colonne a un defaut en base et refuse NULL, donc
      // un champ vide doit repartir a 30, pas disparaitre.
      recruitment_radius_km: Number(values.recruitment_radius_km) || 30,
      schedule: values.schedule || null,
      start_date: values.start_date || null,
      minimum_age: Number(values.minimum_age) || null,
      requires_driving_license: values.requires_driving_license ?? false,
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
          <Label htmlFor="offer-postal-code">Code postal</Label>
          <Input
            id="offer-postal-code"
            inputMode="numeric"
            maxLength={5}
            placeholder="66000"
            aria-invalid={!!errors.postal_code}
            aria-describedby="offer-postal-code-aide"
            {...register('postal_code')}
          />
          {/* Ce n'est pas un champ d'adresse de plus : c'est lui qui place
              l'offre sur la carte, et donc qui la fait exister dans les deux
              piles « Découvrir ». Sans lui, elle reste introuvable sans que
              rien ne le signale. */}
          <p
            id="offer-postal-code-aide"
            className="font-inter text-xs text-muted-foreground"
          >
            Nécessaire pour que l&apos;offre apparaisse dans « Découvrir », côté candidats
            comme côté profils.
          </p>
          {errors.postal_code && (
            <p role="alert" className="text-sm text-destructive">
              {errors.postal_code.message}
            </p>
          )}
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

      {/* Mise en relation : ce qui decide QUI voit cette offre, et qui peut y
          repondre. Regroupe a part plutot que fondu dans le reste du
          formulaire — ce ne sont pas des informations d'annonce, ce sont des
          criteres. */}
      <fieldset className="flex flex-col gap-4 rounded-md border border-border p-4">
        <legend className="px-1 font-poppins text-sm font-semibold">
          Mise en relation
        </legend>
        <p className="font-inter text-sm text-muted-foreground">
          Ces réglages déterminent à quels jeunes cette offre est proposée, et lesquels te
          sont proposés en retour.
        </p>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div className="flex flex-col gap-2">
            <Label htmlFor="offer-sector">Secteur</Label>
            <select id="offer-sector" className={selectClassName} {...register('sector')}>
              <option value="">Non précisé</option>
              {Object.entries(OFFER_SECTOR_LABELS).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="offer-radius">Rayon de recrutement</Label>
            <select
              id="offer-radius"
              className={selectClassName}
              aria-describedby="offer-radius-aide"
              {...register('recruitment_radius_km')}
            >
              {RECRUITMENT_RADIUS_OPTIONS.map((km) => (
                <option key={km} value={km}>
                  {km} km
                </option>
              ))}
            </select>
            <p
              id="offer-radius-aide"
              className="font-inter text-xs text-muted-foreground"
            >
              Jusqu&apos;où tu acceptes qu&apos;un candidat vienne. Sa propre zone de
              mobilité doit aussi couvrir ton offre.
            </p>
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="offer-start-date">Date de début (facultatif)</Label>
            <Input id="offer-start-date" type="date" {...register('start_date')} />
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="offer-schedule">Rythme / horaires (facultatif)</Label>
            <Input
              id="offer-schedule"
              placeholder="35h, samedi travaillé"
              {...register('schedule')}
            />
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="offer-minimum-age">Âge minimum (facultatif)</Label>
            <Input
              id="offer-minimum-age"
              inputMode="numeric"
              placeholder="16"
              aria-invalid={!!errors.minimum_age}
              aria-describedby="offer-minimum-age-aide"
              {...register('minimum_age')}
            />
            {/* 16 ans est deja le plancher de l'application : ce champ ne sert
                qu'aux postes ou la loi ou l'assurance exigent davantage. */}
            <p
              id="offer-minimum-age-aide"
              className="font-inter text-xs text-muted-foreground"
            >
              À renseigner seulement si le poste l&apos;impose (16 ans minimum partout).
            </p>
            {errors.minimum_age && (
              <p role="alert" className="text-sm text-destructive">
                {errors.minimum_age.message}
              </p>
            )}
          </div>

          <div className="flex items-start gap-2 sm:pt-8">
            <input
              id="offer-requires-license"
              type="checkbox"
              className="mt-1 h-4 w-4 rounded border-input"
              {...register('requires_driving_license')}
            />
            <Label htmlFor="offer-requires-license" className="font-normal">
              Le permis est indispensable pour ce poste
            </Label>
          </div>
        </div>
      </fieldset>

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
