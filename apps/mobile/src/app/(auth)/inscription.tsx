import { zodResolver } from '@hookform/resolvers/zod';
import { UserRole } from '@jeuncy/shared';
import { Link } from 'expo-router';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { StyleSheet, View } from 'react-native';
import { z } from 'zod';

import { BrandHeader } from '@/components/brand-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { ChoiceGroup, type Choice } from '@/components/ui/choice-group';
import { Field } from '@/components/ui/field';
import { Screen } from '@/components/ui/screen';
import { Text } from '@/components/ui/text';
import { register } from '@/lib/api/auth';
import { ApiError } from '@/lib/api/client';
import { spacing } from '@/theme/typography';

// ADMIN et STAFF sont volontairement absents : ce sont des roles internes,
// attribues en base, jamais choisis a l'inscription. Le serveur applique la
// meme garde de son cote (RegisterRequest).
const ROLES: readonly Choice<'CANDIDATE' | 'COMPANY' | 'CFA'>[] = [
  {
    value: UserRole.CANDIDATE,
    label: 'Je cherche',
    hint: 'Alternance, job saisonnier, bénévolat',
  },
  { value: UserRole.COMPANY, label: 'Je recrute', hint: 'Entreprise' },
  { value: UserRole.CFA, label: 'Je forme', hint: 'Centre de formation (CFA)' },
] as const;

const schema = z.object({
  email: z
    .string()
    .min(1, 'Renseigne ton adresse email.')
    .email('Adresse email invalide.'),
  password: z.string().min(8, 'Le mot de passe doit faire au moins 8 caractères.'),
  role: z.enum([UserRole.CANDIDATE, UserRole.COMPANY, UserRole.CFA]),
  // Age minimum de 15 ans : en France, un mineur peut consentir seul au
  // traitement de ses donnees a partir de cet age. En dessous, l'accord d'un
  // titulaire de l'autorite parentale serait requis (MOBILE.md section 9.3).
  // Declaratif, comme partout : il s'agit d'etre explicite sur la regle, pas
  // de verifier un etat civil.
  ageConfirmed: z.literal(true, {
    errorMap: () => ({ message: 'Tu dois avoir 15 ans ou plus pour créer un compte.' }),
  }),
});

type FormValues = z.infer<typeof schema>;

export default function InscriptionScreen() {
  const [erreurServeur, setErreurServeur] = useState<string | null>(null);
  const {
    control,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      email: '',
      password: '',
      role: UserRole.CANDIDATE,
      ageConfirmed: false as unknown as true,
    },
  });

  const onSubmit = async (values: FormValues) => {
    setErreurServeur(null);
    try {
      await register(values.email.trim(), values.password, values.role);
    } catch (error) {
      setErreurServeur(
        error instanceof ApiError ? error.message : 'Une erreur est survenue.',
      );
    }
  };

  return (
    <Screen>
      <BrandHeader title="Créer un compte" subtitle="Quelques secondes suffisent." />

      <View style={styles.form}>
        <Controller
          control={control}
          name="role"
          render={({ field: { onChange, value } }) => (
            <ChoiceGroup
              label="Je suis"
              choices={ROLES}
              value={value}
              onChange={onChange}
            />
          )}
        />

        <Controller
          control={control}
          name="email"
          render={({ field: { onChange, onBlur, value } }) => (
            <Field
              label="Adresse email"
              value={value}
              onChangeText={onChange}
              onBlur={onBlur}
              error={errors.email?.message}
              autoCapitalize="none"
              autoComplete="email"
              keyboardType="email-address"
              textContentType="emailAddress"
              placeholder="prenom.nom@exemple.fr"
            />
          )}
        />

        <Controller
          control={control}
          name="password"
          render={({ field: { onChange, onBlur, value } }) => (
            <Field
              label="Mot de passe"
              value={value}
              onChangeText={onChange}
              onBlur={onBlur}
              error={errors.password?.message}
              secureTextEntry
              autoComplete="new-password"
              textContentType="newPassword"
              placeholder="8 caractères minimum"
            />
          )}
        />

        <Controller
          control={control}
          name="ageConfirmed"
          render={({ field: { onChange, value } }) => (
            <Checkbox
              label="J'ai 15 ans ou plus."
              checked={Boolean(value)}
              onChange={onChange}
              error={errors.ageConfirmed?.message}
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
          label="Créer mon compte"
          onPress={handleSubmit(onSubmit)}
          loading={isSubmitting}
        />
      </View>

      <View style={styles.pied}>
        <Text variant="small" tone="muted">
          Tu as déjà un compte ?
        </Text>
        <Link href="/connexion">
          <Text variant="bodyStrong" tone="accent">
            Se connecter
          </Text>
        </Link>
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  form: { gap: spacing.lg },
  pied: { marginTop: spacing.xxl, alignItems: 'center', gap: spacing.xs },
});
