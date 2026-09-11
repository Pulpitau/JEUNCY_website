import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation } from '@tanstack/react-query';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
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
  addExperience,
  updateExperience,
  type ExperienceInput,
} from '@/lib/api/candidate-profile';
import { ApiError } from '@/lib/api/client';
import { spacing } from '@/theme/typography';

const schema = z
  .object({
    title: z.string().trim().min(1, "Renseigne l'intitulé du poste.").max(255),
    company: z.string().trim().min(1, "Renseigne l'entreprise.").max(255),
    location: z
      .string()
      .trim()
      .max(255)
      .transform((v) => v || null),
    start_date: z.string().min(1, 'Renseigne la date de début.'),
    end_date: z.string().nullable(),
    description: z
      .string()
      .trim()
      .max(2000, '2000 caractères maximum.')
      .transform((v) => v || null),
  })
  .refine((v) => !v.end_date || v.end_date >= v.start_date, {
    message: 'La fin doit être après le début.',
    path: ['end_date'],
  });

type FormValues = z.input<typeof schema>;
type Parsed = z.output<typeof schema>;

// Ajout ou modification d'une experience, selon qu'un id est passe en
// parametre. L'element a modifier est lu dans le profil deja en cache : pas
// de requete supplementaire, l'ecran s'ouvre instantanement.
export default function ExperienceScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id?: string }>();
  const profile = useCandidateProfile();
  const invalidate = useInvalidateProfile();
  const [erreurServeur, setErreurServeur] = useState<string | null>(null);

  const existing = id
    ? (profile.data?.experiences.find((e) => String(e.id) === id) ?? null)
    : null;

  const {
    control,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues, unknown, Parsed>({
    resolver: zodResolver(schema),
    defaultValues: {
      title: existing?.title ?? '',
      company: existing?.company ?? '',
      location: existing?.location ?? '',
      start_date: existing?.start_date?.slice(0, 10) ?? '',
      end_date: existing?.end_date?.slice(0, 10) ?? null,
      description: existing?.description ?? '',
    },
  });

  const save = useMutation({
    mutationFn: (input: ExperienceInput) =>
      existing ? updateExperience(existing.id, input) : addExperience(input),
    onSuccess: async () => {
      await invalidate();
      router.back();
    },
    onError: (error) =>
      setErreurServeur(
        error instanceof ApiError ? error.message : 'Une erreur est survenue.',
      ),
  });

  return (
    <>
      <Stack.Screen
        options={{ title: existing ? "Modifier l'expérience" : 'Nouvelle expérience' }}
      />
      <Screen hasHeader>
        <View style={styles.form}>
          <Controller
            control={control}
            name="title"
            render={({ field: { onChange, onBlur, value } }) => (
              <Field
                label="Poste *"
                value={value}
                onChangeText={onChange}
                onBlur={onBlur}
                error={errors.title?.message}
                placeholder="Ex : Vendeur, serveuse, assistant marketing"
              />
            )}
          />
          <Controller
            control={control}
            name="company"
            render={({ field: { onChange, onBlur, value } }) => (
              <Field
                label="Entreprise *"
                value={value}
                onChangeText={onChange}
                onBlur={onBlur}
                error={errors.company?.message}
              />
            )}
          />
          <Controller
            control={control}
            name="location"
            render={({ field: { onChange, onBlur, value } }) => (
              <Field
                label="Lieu"
                value={value ?? ''}
                onChangeText={onChange}
                onBlur={onBlur}
                error={errors.location?.message}
                autoCapitalize="words"
              />
            )}
          />
          <Controller
            control={control}
            name="start_date"
            render={({ field: { onChange, value } }) => (
              <DateField
                label="Début *"
                value={value || null}
                onChange={(iso) => onChange(iso ?? '')}
                error={errors.start_date?.message}
                maximumDate={new Date()}
              />
            )}
          />
          <Controller
            control={control}
            name="end_date"
            render={({ field: { onChange, value } }) => (
              <DateField
                label="Fin"
                value={value ?? null}
                onChange={onChange}
                error={errors.end_date?.message}
                placeholder="Toujours en poste"
                clearable
                clearLabel="Toujours en poste"
              />
            )}
          />
          <Controller
            control={control}
            name="description"
            render={({ field: { onChange, onBlur, value } }) => (
              <Field
                label="Description"
                value={value ?? ''}
                onChangeText={onChange}
                onBlur={onBlur}
                error={errors.description?.message}
                multiline
                numberOfLines={5}
                style={styles.multiline}
                placeholder="Tes missions, ce que tu as appris."
              />
            )}
          />

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
            label="Enregistrer"
            onPress={handleSubmit((values) => {
              setErreurServeur(null);
              save.mutate(values);
            })}
            loading={isSubmitting || save.isPending}
          />
        </View>
      </Screen>
    </>
  );
}

const styles = StyleSheet.create({
  form: { gap: spacing.lg },
  multiline: { minHeight: 120, textAlignVertical: 'top' },
});
