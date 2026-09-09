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
import { forgotPassword } from '@/lib/api/auth';
import { ApiError } from '@/lib/api/client';
import { spacing } from '@/theme/typography';

const schema = z.object({
  email: z
    .string()
    .min(1, 'Renseigne ton adresse email.')
    .email('Adresse email invalide.'),
});

type FormValues = z.infer<typeof schema>;

export default function MotDePasseOublieScreen() {
  const [envoye, setEnvoye] = useState(false);
  const [erreurServeur, setErreurServeur] = useState<string | null>(null);
  const {
    control,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: '' },
  });

  const onSubmit = async (values: FormValues) => {
    setErreurServeur(null);
    try {
      await forgotPassword(values.email.trim());
      setEnvoye(true);
    } catch (error) {
      setErreurServeur(
        error instanceof ApiError ? error.message : 'Une erreur est survenue.',
      );
    }
  };

  if (envoye) {
    return (
      <Screen>
        <BrandHeader title="C'est envoyé" />
        {/* Message volontairement identique que le compte existe ou non :
            l'API repond deja de la meme facon dans les deux cas, pour ne pas
            reveler quelles adresses sont inscrites. */}
        <Text variant="body" tone="muted">
          Si un compte existe avec cette adresse, tu vas recevoir un email pour choisir un
          nouveau mot de passe. Pense à vérifier tes spams.
        </Text>
        <Link href="/connexion" style={styles.retour}>
          <Text variant="bodyStrong" tone="accent">
            Retour à la connexion
          </Text>
        </Link>
      </Screen>
    );
  }

  return (
    <Screen>
      <BrandHeader
        title="Mot de passe oublié"
        subtitle="On t'envoie un lien pour en choisir un nouveau."
      />

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
              returnKeyType="send"
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
          label="Envoyer le lien"
          onPress={handleSubmit(onSubmit)}
          loading={isSubmitting}
        />

        <Link href="/connexion" style={styles.retour}>
          <Text variant="small" tone="accent">
            Retour à la connexion
          </Text>
        </Link>
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  form: { gap: spacing.lg },
  retour: { alignSelf: 'center', paddingVertical: spacing.lg },
});
