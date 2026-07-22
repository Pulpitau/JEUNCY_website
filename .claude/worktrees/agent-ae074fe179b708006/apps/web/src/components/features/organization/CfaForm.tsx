import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { CfaOrganization, CfaOrganizationInput } from '@/lib/api/cfa-organization';

const cfaSchema = z.object({
  name: z.string().min(1, 'Le nom du CFA est requis.'),
  city: z.string().optional().or(z.literal('')),
  website: z.string().url('URL invalide.').optional().or(z.literal('')),
  description: z.string().optional().or(z.literal('')),
});

type CfaFormValues = z.infer<typeof cfaSchema>;

interface CfaFormProps {
  cfaOrganization: CfaOrganization | null;
  onSubmit: (values: CfaOrganizationInput) => Promise<unknown>;
  isSubmitting: boolean;
}

export function CfaForm({ cfaOrganization, onSubmit, isSubmitting }: CfaFormProps) {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<CfaFormValues>({
    resolver: zodResolver(cfaSchema),
    defaultValues: {
      name: cfaOrganization?.name ?? '',
      city: cfaOrganization?.city ?? '',
      website: cfaOrganization?.website ?? '',
      description: cfaOrganization?.description ?? '',
    },
  });

  async function handleFormSubmit(values: CfaFormValues) {
    await onSubmit({
      name: values.name,
      city: values.city || null,
      website: values.website || null,
      description: values.description || null,
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
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="cfa-description">Description</Label>
        <Textarea id="cfa-description" rows={4} {...register('description')} />
      </div>

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
    </form>
  );
}
