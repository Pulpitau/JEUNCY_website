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
import type { Company, CompanyInput } from '@/lib/api/company';

const companySchema = z.object({
  name: z.string().min(1, "Le nom de l'entreprise est requis."),
  siret: z
    .string()
    .regex(/^\d{14}$/, 'Le SIRET doit contenir 14 chiffres.')
    .optional()
    .or(z.literal('')),
  city: z.string().optional().or(z.literal('')),
  website: z.string().url('URL invalide.').optional().or(z.literal('')),
  description: z.string().optional().or(z.literal('')),
  work_mode: z.union([z.nativeEnum(WorkMode), z.literal('')]).optional(),
});

type CompanyFormValues = z.infer<typeof companySchema>;

interface CompanyFormProps {
  company: Company | null;
  onSubmit: (values: CompanyInput) => Promise<unknown>;
  onCancel?: () => void;
  isSubmitting: boolean;
}

export function CompanyForm({
  company,
  onSubmit,
  onCancel,
  isSubmitting,
}: CompanyFormProps) {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<CompanyFormValues>({
    resolver: zodResolver(companySchema),
    defaultValues: {
      name: company?.name ?? '',
      siret: company?.siret ?? '',
      city: company?.city ?? '',
      website: company?.website ?? '',
      description: company?.description ?? '',
      work_mode: company?.work_mode ?? '',
    },
  });

  async function handleFormSubmit(values: CompanyFormValues) {
    await onSubmit({
      name: values.name,
      siret: values.siret || null,
      city: values.city || null,
      website: values.website || null,
      description: values.description || null,
      work_mode: values.work_mode || null,
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
          <Label htmlFor="company-name">Nom de l'entreprise</Label>
          <Input id="company-name" aria-invalid={!!errors.name} {...register('name')} />
          {errors.name && (
            <p role="alert" className="text-sm text-destructive">
              {errors.name.message}
            </p>
          )}
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="company-siret">SIRET</Label>
          <Input
            id="company-siret"
            aria-invalid={!!errors.siret}
            {...register('siret')}
          />
          {errors.siret && (
            <p role="alert" className="text-sm text-destructive">
              {errors.siret.message}
            </p>
          )}
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="company-city">Ville</Label>
          <Input id="company-city" {...register('city')} />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="company-website">Site web</Label>
          <Input
            id="company-website"
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
          <Label htmlFor="company-work-mode">Mode de travail</Label>
          <select
            id="company-work-mode"
            className={cn(
              'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm font-inter focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
            )}
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
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="company-description">Description</Label>
        <Textarea id="company-description" rows={4} {...register('description')} />
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
            : company
              ? 'Mettre à jour'
              : "Créer l'entreprise"}
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
