import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { StyleSheet, View } from 'react-native';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import { ChoiceGroup } from '@/components/ui/choice-group';
import { Field } from '@/components/ui/field';
import { Screen } from '@/components/ui/screen';
import { Text } from '@/components/ui/text';
import { useInvalidateProfile } from '@/hooks/use-candidate-profile';
import { addLanguage } from '@/lib/api/candidate-profile';
import { ApiError } from '@/lib/api/client';
import { spacing } from '@/theme/typography';

// Echelle europeenne (CECRL) plus « langue maternelle ». Le web laisse le
// niveau en texte libre ; ici des choix guides, pour que deux candidats
// decrivent le meme niveau avec le meme mot et que les recruteurs comparent.
// Les valeurs envoyees (« B2 », « Natif ») restent lisibles telles quelles
// sur le site et dans le CV genere.
const LEVELS = [
  { value: 'A1', label: 'A1 — Débutant' },
  { value: 'A2', label: 'A2 — Élémentaire' },
  { value: 'B1', label: 'B1 — Intermédiaire' },
  { value: 'B2', label: 'B2 — Courant' },
  { value: 'C1', label: 'C1 — Avancé' },
  { value: 'C2', label: 'C2 — Maîtrise' },
  { value: 'Natif', label: 'Langue maternelle' },
] as const;

type Level = (typeof LEVELS)[number]['value'];

const schema = z.object({
  name: z.string().trim().min(1, 'Renseigne la langue.').max(255),
  level: z.enum(['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'Natif']),
});

type FormValues = z.infer<typeof schema>;

export default function LangueScreen() {
  const router = useRouter();
  const invalidate = useInvalidateProfile();
  const [erreurServeur, setErreurServeur] = useState<string | null>(null);

  const {
    control,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { name: '', level: 'B1' },
  });

  const save = useMutation({
    mutationFn: addLanguage,
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
    <Screen hasHeader>
      <View style={styles.form}>
        <Controller
          control={control}
          name="name"
          render={({ field: { onChange, onBlur, value } }) => (
            <Field
              label="Langue *"
              value={value}
              onChangeText={onChange}
              onBlur={onBlur}
              error={errors.name?.message}
              placeholder="Ex : Anglais, espagnol, arabe"
              autoCapitalize="words"
              autoFocus
            />
          )}
        />
        <Controller
          control={control}
          name="level"
          render={({ field: { onChange, value } }) => (
            <ChoiceGroup<Level>
              label="Niveau *"
              choices={LEVELS}
              value={value}
              onChange={onChange}
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
          label="Ajouter"
          onPress={handleSubmit((values) => {
            setErreurServeur(null);
            save.mutate(values);
          })}
          loading={isSubmitting || save.isPending}
        />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  form: { gap: spacing.lg },
});
