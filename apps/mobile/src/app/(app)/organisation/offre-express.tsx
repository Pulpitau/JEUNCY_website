import { zodResolver } from '@hookform/resolvers/zod';
import { ContractType, OfferSector } from '@jeuncy/shared';
import { useMutation } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { Controller, useForm } from 'react-hook-form';
import { Alert, StyleSheet, View } from 'react-native';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Screen } from '@/components/ui/screen';
import { SelectField } from '@/components/ui/select-field';
import { Text } from '@/components/ui/text';
import { useInvalidateMyOffers } from '@/hooks/use-my-offers';
import { createExpressOffer } from '@/lib/api/job-offers';
import { CONTRACT_TYPE_LABELS, OFFER_SECTOR_LABELS } from '@/lib/labels';
import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

// L'offre express (MOBILE.md §4.1) : cinq champs, une minute, et la pile de
// candidats s'ouvre.
//
// POURQUOI SI COURT. Le formulaire long d'une offre est le mur contre lequel
// bute un employeur venu essayer. Le deck ne doit jamais etre verrouille
// derriere lui : ce qu'il faut vraiment pour proposer des candidats, c'est
// un intitule, un contrat, un lieu et un secteur. Le reste — description,
// remuneration, missions — se complete depuis « Mes offres », sur un
// ordinateur, quand l'employeur a decide que ca valait le coup.
//
// Le code postal est requis ici et pas seulement a la publication : une
// offre express est publiee dans la foulee, et sans code postal elle
// n'apparaitrait dans aucune pile (JOB_OFFER_NOT_LOCATED).

const schema = z.object({
  title: z
    .string()
    .trim()
    .min(1, 'Donne un intitulé au poste.')
    .max(255, '255 caractères maximum.'),
  city: z
    .string()
    .trim()
    .min(1, 'Indique la commune du poste.')
    .max(255, '255 caractères maximum.'),
  postal_code: z
    .string()
    .trim()
    .regex(/^\d{5}$/, 'Un code postal à 5 chiffres.'),
  // Nullable plutot qu'optionnel : le champ existe des le depart avec la
  // valeur « rien choisi », ce qui permet d'afficher l'erreur sous le bon
  // selecteur au lieu d'une alerte generique.
  contract_type: z.nativeEnum(ContractType, {
    invalid_type_error: 'Choisis un type de contrat.',
  }),
  sector: z.nativeEnum(OfferSector, { invalid_type_error: 'Choisis un secteur.' }),
});

type FormValues = z.infer<typeof schema>;

const CONTRACT_OPTIONS = Object.values(ContractType).map((value) => ({
  value,
  label: CONTRACT_TYPE_LABELS[value],
}));

const SECTOR_OPTIONS = Object.values(OfferSector).map((value) => ({
  value,
  label: OFFER_SECTOR_LABELS[value],
}));

export default function OffreExpressScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const invalidateOffers = useInvalidateMyOffers();

  const {
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    // Les deux enums partent a null : « pas encore choisi » est un etat
    // legitime du formulaire, et zod le refusera a l'envoi avec un message
    // place sous le bon selecteur.
    defaultValues: {
      title: '',
      city: '',
      postal_code: '',
      contract_type: null as unknown as ContractType,
      sector: null as unknown as OfferSector,
    },
  });

  const mutation = useMutation({
    mutationFn: createExpressOffer,
    onSuccess: () => {
      invalidateOffers();
      // Retour a la pile : l'offre vient d'etre publiee, les candidats
      // eligibles sont deja calculables. C'est la recompense du formulaire.
      router.back();
    },
    onError: (error: Error) => {
      Alert.alert("L'offre n'a pas été créée", error.message);
    },
  });

  const submit = handleSubmit((values) =>
    mutation.mutate({
      title: values.title.trim(),
      city: values.city.trim(),
      postal_code: values.postal_code.trim(),
      contract_type: values.contract_type,
      sector: values.sector,
    }),
  );

  return (
    <Screen hasHeader>
      <Text variant="hero">Une offre en une minute</Text>
      <Text variant="body" tone="muted" style={styles.intro}>
        De quoi ouvrir ta pile de candidats tout de suite. Tu compléteras la description,
        la rémunération et les missions plus tard, depuis « Mes offres ».
      </Text>

      <View style={styles.form}>
        <Controller
          control={control}
          name="title"
          render={({ field }) => (
            <Field
              label="Intitulé du poste"
              value={field.value}
              onChangeText={field.onChange}
              onBlur={field.onBlur}
              error={errors.title?.message}
              placeholder="Vendeur conseil en alternance"
              autoCapitalize="sentences"
              returnKeyType="next"
            />
          )}
        />

        <Controller
          control={control}
          name="contract_type"
          render={({ field }) => (
            <SelectField
              label="Type de contrat"
              options={CONTRACT_OPTIONS}
              value={field.value ?? null}
              onChange={field.onChange}
              placeholder="Choisir un contrat"
              error={errors.contract_type?.message}
            />
          )}
        />

        <Controller
          control={control}
          name="city"
          render={({ field }) => (
            <Field
              label="Commune du poste"
              value={field.value}
              onChangeText={field.onChange}
              onBlur={field.onBlur}
              error={errors.city?.message}
              placeholder="Perpignan"
              autoCapitalize="words"
              returnKeyType="next"
            />
          )}
        />

        <Controller
          control={control}
          name="postal_code"
          render={({ field }) => (
            <Field
              label="Code postal"
              value={field.value}
              onChangeText={(text) => field.onChange(text.replace(/\D/g, '').slice(0, 5))}
              onBlur={field.onBlur}
              error={errors.postal_code?.message}
              placeholder="66000"
              keyboardType="number-pad"
              maxLength={5}
              returnKeyType="done"
            />
          )}
        />

        <Controller
          control={control}
          name="sector"
          render={({ field }) => (
            <SelectField
              label="Secteur"
              options={SECTOR_OPTIONS}
              value={field.value ?? null}
              onChange={field.onChange}
              placeholder="Choisir un secteur"
              error={errors.sector?.message}
            />
          )}
        />
      </View>

      <View style={[styles.note, { backgroundColor: colors.surfaceMuted }]}>
        <Text variant="small" tone="muted">
          L&apos;offre est publiée aussitôt et visible des candidats. Le rayon de
          recrutement est de 30 km par défaut, modifiable ensuite.
        </Text>
      </View>

      <Button
        label="Créer et publier"
        onPress={submit}
        loading={mutation.isPending}
        style={styles.submit}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  intro: { marginTop: spacing.xs },
  form: { marginTop: spacing.xl, gap: spacing.md },
  note: { marginTop: spacing.lg, padding: spacing.md, borderRadius: radii.md },
  submit: { marginTop: spacing.lg },
});
