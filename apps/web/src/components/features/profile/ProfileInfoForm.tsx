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
  headline: z.string().optional().or(z.literal('')),
  phone: z.string().optional().or(z.literal('')),
  birth_date: z.string().optional().or(z.literal('')),
  address: z.string().optional().or(z.literal('')),
  city: z.string().optional().or(z.literal('')),
  postal_code: z.string().optional().or(z.literal('')),
  bio: z.string().optional().or(z.literal('')),
  hobbies: z.string().optional().or(z.literal('')),
  driving_license: z.string().optional().or(z.literal('')),
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
      headline: profile?.headline ?? '',
      phone: profile?.phone ?? '',
      birth_date: profile?.birth_date?.slice(0, 10) ?? '',
      address: profile?.address ?? '',
      city: profile?.city ?? '',
      postal_code: profile?.postal_code ?? '',
      bio: profile?.bio ?? '',
      hobbies: profile?.hobbies ?? '',
      driving_license: profile?.driving_license ?? '',
    },
  });

  async function handleFormSubmit(values: ProfileFormValues) {
    await onSubmit({
      first_name: values.first_name,
      last_name: values.last_name,
      headline: values.headline || null,
      phone: values.phone || null,
      birth_date: values.birth_date || null,
      address: values.address || null,
      city: values.city || null,
      postal_code: values.postal_code || null,
      bio: values.bio || null,
      hobbies: values.hobbies || null,
      driving_license: values.driving_license || null,
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
        <div className="flex flex-col gap-2 sm:col-span-2">
          <Label htmlFor="headline">Titre professionnel</Label>
          <Input
            id="headline"
            placeholder="Ex : Chargé de communication digitale"
            {...register('headline')}
          />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="phone">Téléphone</Label>
          <Input id="phone" autoComplete="tel" {...register('phone')} />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="birth_date">Date de naissance</Label>
          <Input
            id="birth_date"
            type="date"
            autoComplete="bday"
            {...register('birth_date')}
          />
        </div>
        <div className="flex flex-col gap-2 sm:col-span-2">
          <Label htmlFor="address">Adresse</Label>
          <Input id="address" autoComplete="street-address" {...register('address')} />
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
        <div className="flex flex-col gap-2">
          <Label htmlFor="driving_license">Permis de conduire</Label>
          <Input
            id="driving_license"
            placeholder="Ex : B"
            {...register('driving_license')}
          />
        </div>
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="bio">Bio</Label>
        <Textarea id="bio" rows={4} {...register('bio')} />
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="hobbies">Loisirs</Label>
        <Textarea
          id="hobbies"
          rows={2}
          placeholder="Ex : Photographie, football, lecture"
          {...register('hobbies')}
        />
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
