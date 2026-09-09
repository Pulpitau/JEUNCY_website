import { zodResolver } from '@hookform/resolvers/zod';
import { Link } from 'expo-router';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { StyleSheet, View } from 'react-native';
import { z } from 'zod';

import { BrandHeader } from '@/components/brand-header';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Screen } from '@/components/ui/screen';
import { Text } from '@/components/ui/text';
import { login } from '@/lib/api/auth';
import { ApiError } from '@/lib/api/client';
import { spacing } from '@/theme/typography';

const schema = z.object({
  email: z
    .string()
    .min(1, 'Renseigne ton adresse email.')
    .email('Adresse email invalide.'),
  password: z.string().min(1, 'Renseigne ton mot de passe.'),
});

type FormValues = z.infer<typeof schema>;

export default function ConnexionScreen() {
  const [erreurServeur, setErreurServeur] = useState<string | null>(null);
  const {
    control,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: '', password: '' },
  });

  const onSubmit = async (values: FormValues) => {
    setErreurServeur(null);
    try {
      await login(values.email.trim(), values.password);
      // Pas de navigation ici : la garde du layout racine reagit a l'arrivee
      // de l'utilisateur dans le store et bascule sur l'accueil.
    } catch (error) {
      setErreurServeur(
        error instanceof ApiError ? error.message : 'Une erreur est survenue.',
      );
    }
  };

  return (
    <Screen>
      <BrandHeader title="Content de te revoir" subtitle="Ton alternance commence ici." />

      <View style={styles.form}>
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
              autoComplete="current-password"
              textContentType="password"
              // Permet de valider au clavier plutot que de viser le bouton.
              returnKeyType="go"
              onSubmitEditing={handleSubmit(onSubmit)}
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
          label="Se connecter"
          onPress={handleSubmit(onSubmit)}
          loading={isSubmitting}
        />

        <Link href="/mot-de-passe-oublie" style={styles.lien}>
          <Text variant="small" tone="accent">
            Mot de passe oublié ?
          </Text>
        </Link>
      </View>

      <View style={styles.pied}>
        <Text variant="small" tone="muted">
          Pas encore de compte ?
        </Text>
        <Link href="/inscription">
          <Text variant="bodyStrong" tone="accent">
            Créer un compte
          </Text>
        </Link>
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  form: { gap: spacing.lg },
  lien: { alignSelf: 'center', paddingVertical: spacing.sm },
  pied: {
    marginTop: spacing.xxl,
    alignItems: 'center',
    gap: spacing.xs,
  },
});
