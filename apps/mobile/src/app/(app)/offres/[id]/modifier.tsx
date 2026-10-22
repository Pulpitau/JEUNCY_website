import { zodResolver } from '@hookform/resolvers/zod';
import { ContractType, OfferSector } from '@jeuncy/shared';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Controller, useForm } from 'react-hook-form';
import { ActivityIndicator, Alert, StyleSheet, View } from 'react-native';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Field } from '@/components/ui/field';
import { Screen } from '@/components/ui/screen';
import { SelectField } from '@/components/ui/select-field';
import { CANDIDATE_DECK_KEY } from '@/hooks/use-candidate-deck';
import { useInvalidateMyOffers, useMyOffers } from '@/hooks/use-my-offers';
import { canEditOffer, updateOffer, type JobOffer } from '@/lib/api/job-offers';
import { CONTRACT_TYPE_LABELS, OFFER_SECTOR_LABELS } from '@/lib/labels';
import { useTheme } from '@/theme/theme-provider';
import { spacing } from '@/theme/typography';

// Modifier une offre : ce qui decide de la pile (intitule, contrat, lieu,
// secteur, rayon) et la description, que l'offre express laisse « a
// completer ». Remuneration, missions et le reste restent sur le site :
// ce sont des champs qu'on ne remplit pas au pouce.
//
// Le code postal est requis meme si le serveur l'accepte vide : une offre
// sans lui n'entre dans aucune pile, et cet ecran est justement le chemin
// propose quand il manque.

const RADIUS_STEPS = [5, 10, 20, 30, 50, 100];

const schema = z.object({
  title: z.string().trim().min(1, 'Donne un intitulé au poste.').max(255),
  contract_type: z.nativeEnum(ContractType, {
    invalid_type_error: 'Choisis un type de contrat.',
  }),
  city: z.string().trim().min(1, 'Indique la commune du poste.').max(255),
  postal_code: z
    .string()
    .trim()
    .regex(/^\d{5}$/, 'Un code postal à 5 chiffres.'),
  sector: z.nativeEnum(OfferSector).nullable(),
  recruitment_radius_km: z.string(),
  description: z
    .string()
    .trim()
    .min(1, 'Décris le poste en quelques lignes.')
    .max(5000, '5000 caractères maximum.'),
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

export default function ModifierOffreScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { colors } = useTheme();
  const { data, isPending } = useMyOffers();

  const offer = data?.find((o) => String(o.id) === id) ?? null;

  if (isPending) {
    return (
      <Screen hasHeader>
        <ActivityIndicator color={colors.accent} />
      </Screen>
    );
  }

  if (!offer || !canEditOffer(offer)) {
    return (
      <Screen hasHeader>
        <EmptyState
          title="Offre non modifiable"
          description="Cette offre n'existe plus ou ne peut plus être modifiée."
        />
      </Screen>
    );
  }

  // Formulaire monte seulement une fois l'offre connue : useForm ne lit ses
  // valeurs par defaut qu'au premier rendu.
  return <OfferForm offer={offer} />;
}

function OfferForm({ offer }: { offer: JobOffer }) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const invalidateOffers = useInvalidateMyOffers();

  // Une offre reglee depuis le site peut porter un rayon hors paliers : on
  // l'ajoute a la liste plutot que d'afficher un champ vide.
  const radiusOptions = [...new Set([...RADIUS_STEPS, offer.recruitment_radius_km])]
    .sort((a, b) => a - b)
    .map((km) => ({ value: String(km), label: `${km} km` }));

  const {
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      title: offer.title,
      contract_type: offer.contract_type,
      city: offer.city ?? '',
      postal_code: offer.postal_code ?? '',
      sector: offer.sector,
      recruitment_radius_km: String(offer.recruitment_radius_km),
      description: offer.description,
    },
  });

  const mutation = useMutation({
    mutationFn: (values: FormValues) =>
      updateOffer(offer.id, {
        ...values,
        recruitment_radius_km: Number(values.recruitment_radius_km),
      }),
    onSuccess: () => {
      invalidateOffers();
      // Lieu ou rayon ont pu changer : la pile de cette offre est a recalculer.
      void queryClient.invalidateQueries({ queryKey: [...CANDIDATE_DECK_KEY, offer.id] });
      router.back();
    },
    onError: (error: Error) => Alert.alert("L'offre n'a pas été modifiée", error.message),
  });

  return (
    <Screen hasHeader>
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
              autoCapitalize="sentences"
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
              value={field.value}
              onChange={field.onChange}
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
              autoCapitalize="words"
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
              value={field.value}
              onChange={field.onChange}
              placeholder="Choisir un secteur"
              clearable
            />
          )}
        />

        <Controller
          control={control}
          name="recruitment_radius_km"
          render={({ field }) => (
            <SelectField
              label="Rayon de recrutement"
              options={radiusOptions}
              value={field.value}
              // Pas de ligne « Aucun » : le rayon n'accepte pas de valeur vide.
              onChange={(value) => value && field.onChange(value)}
            />
          )}
        />

        <Controller
          control={control}
          name="description"
          render={({ field }) => (
            <Field
              label="Description"
              value={field.value}
              onChangeText={field.onChange}
              onBlur={field.onBlur}
              error={errors.description?.message}
              multiline
              numberOfLines={8}
              style={styles.multiline}
              placeholder="Le poste, l'équipe, ce que tu attends du candidat."
            />
          )}
        />

        <Button
          label="Enregistrer"
          onPress={handleSubmit((values) => mutation.mutate(values))}
          loading={mutation.isPending}
        />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  form: { gap: spacing.md },
  multiline: { minHeight: 160, textAlignVertical: 'top' },
});
