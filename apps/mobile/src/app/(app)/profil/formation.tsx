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
  addEducation,
  updateEducation,
  type EducationInput,
} from '@/lib/api/candidate-profile';
import { ApiError } from '@/lib/api/client';
import { spacing } from '@/theme/typography';

const schema = z
  .object({
    degree: z.string().trim().min(1, 'Renseigne le diplôme.').max(255),
    school: z.string().trim().min(1, "Renseigne l'établissement.").max(255),
    field_of_study: z
      .string()
      .trim()
      .max(255)
      .transform((v) => v || null),
    start_date: z.string().min(1, 'Renseigne la date de début.'),
    end_date: z.string().nullable(),
  })
  .refine((v) => !v.end_date || v.end_date >= v.start_date, {
    message: 'La fin doit être après le début.',
    path: ['end_date'],
  });

type FormValues = z.input<typeof schema>;
type Parsed = z.output<typeof schema>;

// Miroir de l'ecran experience, pour une formation.
export default function FormationScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id?: string }>();
  const profile = useCandidateProfile();
  const invalidate = useInvalidateProfile();
  const [erreurServeur, setErreurServeur] = useState<string | null>(null);

  const existing = id
    ? (profile.data?.educations.find((e) => String(e.id) === id) ?? null)
    : null;

  const {
    control,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues, unknown, Parsed>({
    resolver: zodResolver(schema),
    defaultValues: {
      degree: existing?.degree ?? '',
      school: existing?.school ?? '',
      field_of_study: existing?.field_of_study ?? '',
      start_date: existing?.start_date?.slice(0, 10) ?? '',
      end_date: existing?.end_date?.slice(0, 10) ?? null,
    },
  });

  const save = useMutation({
    mutationFn: (input: EducationInput) =>
      existing ? updateEducation(existing.id, input) : addEducation(input),
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
        options={{ title: existing ? 'Modifier la formation' : 'Nouvelle formation' }}
      />
      <Screen hasHeader>
        <View style={styles.form}>
          <Controller
            control={control}
            name="degree"
            render={({ field: { onChange, onBlur, value } }) => (
              <Field
                label="Diplôme *"
                value={value}
                onChangeText={onChange}
                onBlur={onBlur}
                error={errors.degree?.message}
                placeholder="Ex : Bac pro commerce, BTS MCO, CAP cuisine"
              />
            )}
          />
          <Controller
            control={control}
            name="school"
            render={({ field: { onChange, onBlur, value } }) => (
              <Field
                label="Établissement *"
                value={value}
                onChangeText={onChange}
                onBlur={onBlur}
                error={errors.school?.message}
              />
            )}
          />
          <Controller
            control={control}
            name="field_of_study"
            render={({ field: { onChange, onBlur, value } }) => (
              <Field
                label="Spécialité"
                value={value ?? ''}
                onChangeText={onChange}
                onBlur={onBlur}
                error={errors.field_of_study?.message}
                placeholder="Ex : Marketing digital"
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
                placeholder="En cours"
                clearable
                clearLabel="En cours"
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
});
