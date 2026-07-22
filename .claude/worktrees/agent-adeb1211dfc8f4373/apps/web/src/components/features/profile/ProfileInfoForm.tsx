import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type {
  CandidateProfile,
  CandidateProfileInput,
} from '@/lib/api/candidate-profile';

const profileSchema = z.object({
  first_name: z.string().min(1, 'Le prénom est requis.'),
  last_name: z.string().min(1, 'Le nom est requis.'),
  phone: z.string().optional().or(z.literal('')),
  city: z.string().optional().or(z.literal('')),
  postal_code: z.string().optional().or(z.literal('')),
  bio: z.string().optional().or(z.literal('')),
});

type ProfileFormValues = z.infer<typeof profileSchema>;

interface ProfileInfoFormProps {
  profile: CandidateProfile | null;
  onSubmit: (values: CandidateProfileInput) => Promise<unknown>;
  isSubmitting: boolean;
}

export function ProfileInfoForm({
  profile,
  onSubmit,
  isSubmitting,
}: ProfileInfoFormProps) {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<ProfileFormValues>({
    resolver: zodResolver(profileSchema),
    defaultValues: {
      first_name: profile?.first_name ?? '',
      last_name: profile?.last_name ?? '',
      phone: profile?.phone ?? '',
      city: profile?.city ?? '',
      postal_code: profile?.postal_code ?? '',
      bio: profile?.bio ?? '',
    },
  });

  async function handleFormSubmit(values: ProfileFormValues) {
    await onSubmit({
      first_name: values.first_name,
      last_name: values.last_name,
      phone: values.phone || null,
      city: values.city || null,
      postal_code: values.postal_code || null,
      bio: values.bio || null,
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
          <Label htmlFor="first_name">Prénom</Label>
          <Input
            id="first_name"
            aria-invalid={!!errors.first_name}
            {...register('first_name')}
          />
          {errors.first_name && (
            <p role="alert" className="text-sm text-destructive">
              {errors.first_name.message}
            </p>
          )}
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="last_name">Nom</Label>
          <Input
            id="last_name"
            aria-invalid={!!errors.last_name}
            {...register('last_name')}
          />
          {errors.last_name && (
            <p role="alert" className="text-sm text-destructive">
              {errors.last_name.message}
            </p>
          )}
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="phone">Téléphone</Label>
          <Input id="phone" autoComplete="tel" {...register('phone')} />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="city">Ville</Label>
          <Input id="city" autoComplete="address-level2" {...register('city')} />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="postal_code">Code postal</Label>
          <Input
            id="postal_code"
            autoComplete="postal-code"
            {...register('postal_code')}
          />
        </div>
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="bio">Bio</Label>
        <Textarea id="bio" rows={4} {...register('bio')} />
      </div>

      <Button
        type="submit"
        variant="gradient"
        disabled={isSubmitting}
        className="self-start"
      >
        {isSubmitting
          ? 'Enregistrement…'
          : profile
            ? 'Mettre à jour'
            : 'Créer mon profil'}
      </Button>
    </form>
  );
}
