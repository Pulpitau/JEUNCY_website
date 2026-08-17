import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { WorkMode } from '@jeuncy/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { WORK_MODE_LABELS } from '@/lib/work-mode-labels';
import { DIPLOMA_LEVEL_OPTIONS } from '@/lib/diploma-level-options';
import type { CfaOrganization, CfaOrganizationInput } from '@/lib/api/cfa-organization';

const cfaSchema = z.object({
  name: z.string().min(1, 'Le nom du CFA est requis.'),
  siret: z
    .string()
    .regex(/^\d{14}$/, 'Le SIRET doit contenir 14 chiffres.')
    .optional()
    .or(z.literal('')),
  nda_number: z.string().optional().or(z.literal('')),
  qualiopi_number: z.string().optional().or(z.literal('')),
  city: z.string().optional().or(z.literal('')),
  website: z.string().url('URL invalide.').optional().or(z.literal('')),
  description: z.string().optional().or(z.literal('')),
  diplomas_offered: z.string().optional().or(z.literal('')),
  diploma_level: z.string().optional().or(z.literal('')),
  training_mode: z.union([z.nativeEnum(WorkMode), z.literal('')]).optional(),
});

type CfaFormValues = z.infer<typeof cfaSchema>;

interface CfaFormProps {
  cfaOrganization: CfaOrganization | null;
  onSubmit: (values: CfaOrganizationInput) => Promise<unknown>;
  onCancel?: () => void;
  isSubmitting: boolean;
}

export function CfaForm({
  cfaOrganization,
  onSubmit,
  onCancel,
  isSubmitting,
}: CfaFormProps) {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<CfaFormValues>({
    resolver: zodResolver(cfaSchema),
    defaultValues: {
      name: cfaOrganization?.name ?? '',
      siret: cfaOrganization?.siret ?? '',
      nda_number: cfaOrganization?.nda_number ?? '',
      qualiopi_number: cfaOrganization?.qualiopi_number ?? '',
      city: cfaOrganization?.city ?? '',
      website: cfaOrganization?.website ?? '',
      description: cfaOrganization?.description ?? '',
      diplomas_offered: cfaOrganization?.diplomas_offered ?? '',
      diploma_level: cfaOrganization?.diploma_level ?? '',
      training_mode: cfaOrganization?.training_mode ?? '',
    },
  });

  async function handleFormSubmit(values: CfaFormValues) {
    await onSubmit({
      name: values.name,
      siret: values.siret || null,
      nda_number: values.nda_number || null,
      qualiopi_number: values.qualiopi_number || null,
      city: values.city || null,
      website: values.website || null,
      description: values.description || null,
      diplomas_offered: values.diplomas_offered || null,
      diploma_level: values.diploma_level || null,
      training_mode: values.training_mode || null,
    });
  }

  return (
    <form
      onSubmit={handleSubmit(handleFormSubmit)}
      noValidate
      className="flex flex-col gap-4"
    >
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className="flex flex-col gap-2">
          <Label htmlFor="cfa-name">Nom du CFA</Label>
          <Input id="cfa-name" aria-invalid={!!errors.name} {...register('name')} />
          {errors.name && (
            <p role="alert" className="text-sm text-destructive">
              {errors.name.message}
            </p>
          )}
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="cfa-city">Ville</Label>
          <Input id="cfa-city" {...register('city')} />
        </div>
        <div className="flex flex-col gap-2 sm:col-span-2">
          <Label htmlFor="cfa-website">Site web</Label>
          <Input
            id="cfa-website"
            placeholder="https://…"
            aria-invalid={!!errors.website}
            {...register('website')}
          />
          {errors.website && (
            <p role="alert" className="text-sm text-destructive">
              {errors.website.message}
            </p>
          )}
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="cfa-diploma-level">Niveau de diplôme principal</Label>
          <select
            id="cfa-diploma-level"
            className={cn(
              'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm font-inter focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
            )}
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
        <div className="flex flex-col gap-2">
          <Label htmlFor="cfa-training-mode">
            Type de formation (présentiel, hybride, distanciel)
          </Label>
          <select
            id="cfa-training-mode"
            className={cn(
              'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm font-inter focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
            )}
            {...register('training_mode')}
          >
            <option value="">Non précisé</option>
            {Object.entries(WORK_MODE_LABELS).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="cfa-description">Description</Label>
        <Textarea id="cfa-description" rows={4} {...register('description')} />
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="cfa-diplomas-offered">Diplômes et formations proposés</Label>
        <Textarea
          id="cfa-diplomas-offered"
          rows={3}
          placeholder="Ex : CAP Cuisine, Bac Pro Commerce, BTS MCO, Licence Pro Marketing (du CAP au Bac+3)…"
          {...register('diplomas_offered')}
        />
        <p className="font-inter text-xs text-muted-foreground">
          Affiché sur ta page publique pour rassurer les visiteurs sur ton offre de
          formation.
        </p>
      </div>

      <div className="flex flex-col gap-4 rounded-md border border-border p-4">
        <div>
          <p className="font-poppins text-sm font-semibold text-foreground">
            Informations légales
          </p>
          <p className="font-inter text-xs text-muted-foreground">
            Affichées sur ta page publique : elles rassurent les candidats et leurs
            familles sur le sérieux de ton organisme de formation.
          </p>
        </div>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div className="flex flex-col gap-2">
            <Label htmlFor="cfa-siret">SIRET</Label>
            <Input id="cfa-siret" aria-invalid={!!errors.siret} {...register('siret')} />
            {errors.siret && (
              <p role="alert" className="text-sm text-destructive">
                {errors.siret.message}
              </p>
            )}
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="cfa-nda">Numéro NDA</Label>
            <Input
              id="cfa-nda"
              placeholder="Ex : 11 75 12345 75"
              {...register('nda_number')}
            />
            <p className="font-inter text-xs text-muted-foreground">
              Numéro de Déclaration d'Activité, délivré par la DREETS.
            </p>
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="cfa-qualiopi">Numéro de certification QUALIOPI</Label>
            <Input id="cfa-qualiopi" {...register('qualiopi_number')} />
            <p className="font-inter text-xs text-muted-foreground">
              Obligatoire pour dispenser des formations financées par des fonds publics ou
              mutualisés.
            </p>
          </div>
        </div>
      </div>

      <div className="flex gap-2">
        <Button
          type="submit"
          variant="gradient"
          disabled={isSubmitting}
          className="self-start"
        >
          {isSubmitting
            ? 'Enregistrement…'
            : cfaOrganization
              ? 'Mettre à jour'
              : 'Créer le CFA'}
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
