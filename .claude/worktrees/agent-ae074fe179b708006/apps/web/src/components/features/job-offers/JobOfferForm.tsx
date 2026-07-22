import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { ContractType } from '@jeuncy/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { JobOffer, JobOfferInput } from '@/lib/api/job-offers';

const CONTRACT_TYPE_LABELS: Record<string, string> = {
  [ContractType.ALTERNANCE]: 'Alternance',
  [ContractType.SAISONNIER]: 'Saisonnier',
  [ContractType.BENEVOLAT]: 'Bénévolat',
};

const jobOfferSchema = z.object({
  title: z.string().min(1, "L'intitulé est requis."),
  description: z.string().min(1, 'La description est requise.'),
  contract_type: z.enum([
    ContractType.ALTERNANCE,
    ContractType.SAISONNIER,
    ContractType.BENEVOLAT,
  ]),
  city: z.string().optional().or(z.literal('')),
});

type JobOfferFormValues = z.infer<typeof jobOfferSchema>;

interface JobOfferFormProps {
  offer?: JobOffer;
  onSubmit: (values: JobOfferInput) => Promise<unknown>;
  onCancel?: () => void;
  isSubmitting: boolean;
}

export function JobOfferForm({
  offer,
  onSubmit,
  onCancel,
  isSubmitting,
}: JobOfferFormProps) {
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
    },
  });

  async function handleFormSubmit(values: JobOfferFormValues) {
    await onSubmit({
      title: values.title,
      description: values.description,
      contract_type: values.contract_type,
      city: values.city || null,
    });
  }

  return (
    <form
      onSubmit={handleSubmit(handleFormSubmit)}
      noValidate
      className="flex flex-col gap-4 rounded-md border border-border p-4"
    >
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className="flex flex-col gap-2">
          <Label htmlFor="offer-title">Intitulé du poste</Label>
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
            className={cn(
              'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm font-inter focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
            )}
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
