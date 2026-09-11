import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { StyleSheet, View } from 'react-native';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import { DateField } from '@/components/ui/date-field';
import { Field } from '@/components/ui/field';
import { Screen } from '@/components/ui/screen';
import { Text } from '@/components/ui/text';
import { useCandidateProfile, useInvalidateProfile } from '@/hooks/use-candidate-profile';
import {
  createProfile,
  updateProfile,
  type CandidateProfileInput,
} from '@/lib/api/candidate-profile';
import { ApiError } from '@/lib/api/client';
import { yearsAgo } from '@/lib/dates';
import { spacing } from '@/theme/typography';

// Champ texte facultatif : une chaine vide devient null pour l'API, qui
// distingue « pas renseigne » (null) d'une valeur.
const optionalText = (max: number) =>
  z
    .string()
    .trim()
    .max(max, `${max} caractères maximum.`)
    .transform((value) => value || null);

const optionalUrl = z
  .string()
  .trim()
  .max(255)
  .refine((value) => value === '' || /^https?:\/\/\S+$/i.test(value), {
    message: 'Adresse invalide : elle doit commencer par https://',
  })
  .transform((value) => value || null);

const schema = z.object({
  first_name: z.string().trim().min(1, 'Renseigne ton prénom.').max(255),
  last_name: z.string().trim().min(1, 'Renseigne ton nom.').max(255),
  // Obligatoire cote serveur depuis le 2026-09-11 : l'age est un critere de
  // selection pour les entreprises (le cout d'un alternant en depend).
  birth_date: z.string().min(1, 'Renseigne ta date de naissance.'),
  headline: optionalText(255),
  phone: z
    .string()
    .trim()
    .max(20, '20 caractères maximum.')
    .regex(/^[0-9 .+-]*$/, 'Chiffres, espaces, points, + et - uniquement.')
    .transform((value) => value || null),
  address: optionalText(255),
  city: optionalText(255),
  postal_code: z
    .string()
    .trim()
    .max(10)
    .regex(/^[0-9]*$/, 'Chiffres uniquement.')
    .transform((value) => value || null),
  bio: optionalText(2000),
  hobbies: optionalText(500),
  driving_license: optionalText(100),
  video_url: optionalUrl,
  portfolio_url: optionalUrl,
  linkedin_url: optionalUrl,
});

type FormValues = z.input<typeof schema>;
type Parsed = z.output<typeof schema>;

export default function InformationsScreen() {
  const router = useRouter();
  const profile = useCandidateProfile();
  const invalidate = useInvalidateProfile();
  const [erreurServeur, setErreurServeur] = useState<string | null>(null);
  const existing = profile.data ?? null;

  const {
    control,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues, unknown, Parsed>({
    resolver: zodResolver(schema),
    defaultValues: {
      first_name: existing?.first_name ?? '',
      last_name: existing?.last_name ?? '',
      birth_date: existing?.birth_date?.slice(0, 10) ?? '',
      headline: existing?.headline ?? '',
      phone: existing?.phone ?? '',
      address: existing?.address ?? '',
      city: existing?.city ?? '',
      postal_code: existing?.postal_code ?? '',
      bio: existing?.bio ?? '',
      hobbies: existing?.hobbies ?? '',
      driving_license: existing?.driving_license ?? '',
      video_url: existing?.video_url ?? '',
      portfolio_url: existing?.portfolio_url ?? '',
      linkedin_url: existing?.linkedin_url ?? '',
    },
  });

  const save = useMutation({
    mutationFn: (input: CandidateProfileInput) =>
      existing ? updateProfile(input) : createProfile(input),
    onSuccess: async () => {
      await invalidate();
      router.back();
    },
    onError: (error) =>
      setErreurServeur(
        error instanceof ApiError ? error.message : 'Une erreur est survenue.',
      ),
  });

  const onSubmit = (values: Parsed) => {
    setErreurServeur(null);
    save.mutate(values);
  };

  // Champ texte branche sur react-hook-form : evite de repeter le Controller
  // quatorze fois.
  const champ = (
    name: keyof FormValues,
    label: string,
    props: Partial<React.ComponentProps<typeof Field>> = {},
  ) => (
    <Controller
      control={control}
      name={name}
      render={({ field: { onChange, onBlur, value } }) => (
        <Field
          label={label}
          value={value ?? ''}
          onChangeText={onChange}
          onBlur={onBlur}
          error={errors[name]?.message}
          {...props}
        />
      )}
    />
  );

  return (
    <Screen hasHeader>
      <View style={styles.form}>
        <Text variant="sectionTitle">Identité</Text>
        {champ('first_name', 'Prénom *', {
          autoComplete: 'given-name',
          textContentType: 'givenName',
        })}
        {champ('last_name', 'Nom *', {
          autoComplete: 'family-name',
          textContentType: 'familyName',
        })}
        <Controller
          control={control}
          name="birth_date"
          render={({ field: { onChange, value } }) => (
            <DateField
              label="Date de naissance *"
              value={value || null}
              onChange={(iso) => onChange(iso ?? '')}
              error={errors.birth_date?.message}
              // 15 ans minimum, comme le serveur ; 100 ans, borne de bon sens.
              maximumDate={yearsAgo(15)}
              minimumDate={yearsAgo(100)}
            />
          )}
        />
        {champ('headline', 'Ce que tu recherches', {
          placeholder: 'Ex : Alternance en communication digitale',
        })}

        <Text variant="sectionTitle" style={styles.sectionTitle}>
          Contact
        </Text>
        {champ('phone', 'Téléphone', {
          keyboardType: 'phone-pad',
          autoComplete: 'tel',
          textContentType: 'telephoneNumber',
        })}
        {champ('address', 'Adresse postale', {
          placeholder: 'Ex : 12 rue des Écoles',
          autoComplete: 'street-address',
        })}
        {champ('city', 'Ville', { autoCapitalize: 'words' })}
        {champ('postal_code', 'Code postal', {
          keyboardType: 'number-pad',
          autoComplete: 'postal-code',
        })}

        <Text variant="sectionTitle" style={styles.sectionTitle}>
          À propos de toi
        </Text>
        {champ('bio', 'Bio', {
          multiline: true,
          numberOfLines: 5,
          style: styles.multiline,
          placeholder: 'Quelques lignes sur toi, ton parcours, ce qui te motive.',
        })}
        {champ('hobbies', 'Loisirs', {
          placeholder: 'Ex : Photographie, football, lecture',
        })}
        {champ('driving_license', 'Permis de conduire', { placeholder: 'Ex : B' })}

        <Text variant="sectionTitle" style={styles.sectionTitle}>
          Liens
        </Text>
        {champ('video_url', 'Vidéo de présentation', {
          placeholder: 'Lien YouTube, Vimeo…',
          keyboardType: 'url',
          autoCapitalize: 'none',
        })}
        {champ('portfolio_url', 'Portfolio', {
          placeholder: 'https://…',
          keyboardType: 'url',
          autoCapitalize: 'none',
        })}
        {champ('linkedin_url', 'LinkedIn', {
          placeholder: 'https://linkedin.com/in/…',
          keyboardType: 'url',
          autoCapitalize: 'none',
        })}

        {erreurServeur ? (
          <Text
            variant="small"
            tone="danger"
            accessibilityLiveRegion="polite"
            role="alert"
          >
            {erreurServeur}
          </Text>
        ) : null}

        <Button
          label={existing ? 'Enregistrer' : 'Créer mon profil'}
          onPress={handleSubmit(onSubmit)}
          loading={isSubmitting || save.isPending}
        />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  form: { gap: spacing.lg },
  sectionTitle: { marginTop: spacing.md },
  multiline: { minHeight: 120, textAlignVertical: 'top' },
});
