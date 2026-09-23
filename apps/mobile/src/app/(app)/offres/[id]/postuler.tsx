import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { ActivityIndicator, Alert, StyleSheet, View } from 'react-native';
import { z } from 'zod';

import {
  openPdf,
  pickPdf,
  useGeneratedCvs,
} from '@/components/features/profile/cv-section';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ChoiceGroup } from '@/components/ui/choice-group';
import { EmptyState } from '@/components/ui/empty-state';
import { Field } from '@/components/ui/field';
import { Screen } from '@/components/ui/screen';
import { Text } from '@/components/ui/text';
import { useCandidateProfile } from '@/hooks/use-candidate-profile';
import { useInvalidateApplications } from '@/hooks/use-my-applications';
import { MATCHES_KEY } from '@/hooks/use-matches';
import { applyToOffer } from '@/lib/api/applications';
import type { NativeFile } from '@/lib/api/candidate-profile';
import { ApiError } from '@/lib/api/client';
import { getPublicOffer, publisherOf } from '@/lib/api/job-offers';
import { formatDateFr } from '@/lib/dates';
import { useTheme } from '@/theme/theme-provider';
import { spacing } from '@/theme/typography';

type CvMode = 'GENERATED' | 'UPLOAD';

const schema = z.object({
  contact_phone: z
    .string()
    .trim()
    .min(1, 'Renseigne un numéro où te joindre.')
    .max(20, '20 caractères maximum.')
    .regex(/^[0-9 .+-]*$/, 'Chiffres, espaces, points, + et - uniquement.'),
  cover_letter: z.string().trim().max(3000, '3000 caractères maximum.'),
});

type FormValues = z.infer<typeof schema>;

// Formulaire de candidature, equivalent de ApplyToOfferSection (web) : le
// telephone est pre-rempli depuis le profil, le CV est soit la derniere
// version generee par Jeuncy, soit un PDF choisi sur le telephone, la lettre
// est facultative. Le serveur exige exactement l'un des deux CV
// (StoreApplicationRequest, required_without).
export default function PostulerScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const { colors } = useTheme();
  const offerId = Number(id);
  const invalidateApplications = useInvalidateApplications();
  const queryClient = useQueryClient();

  const offer = useQuery({
    queryKey: ['job-offers', offerId],
    queryFn: () => getPublicOffer(offerId),
  });
  const profile = useCandidateProfile();
  const cvs = useGeneratedCvs();

  const [cvMode, setCvMode] = useState<CvMode | null>(null);
  const [cvFile, setCvFile] = useState<NativeFile | null>(null);
  const [erreurServeur, setErreurServeur] = useState<string | null>(null);

  const {
    control,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { contact_phone: '', cover_letter: '' },
  });

  // Un CV archive n'a plus de fichier sur le serveur : on ne propose que les
  // autres, et le plus recent en premier (l'API trie).
  const dernierCv = (cvs.data ?? []).find((cv) => !cv.archived_at) ?? null;

  // Pre-remplissage une fois les donnees arrivees, sans ecraser une saisie.
  useEffect(() => {
    if (profile.data?.phone)
      setValue('contact_phone', profile.data.phone, { shouldDirty: false });
  }, [profile.data?.phone, setValue]);

  // Mode par defaut derive, pas stocke : tant que le candidat n'a pas choisi,
  // on propose son CV Jeuncy s'il en a un, sinon le PDF.
  const effectiveCvMode: CvMode = cvMode ?? (dernierCv ? 'GENERATED' : 'UPLOAD');

  const apply = useMutation({
    mutationFn: (values: FormValues) =>
      applyToOffer(offerId, {
        contactPhone: values.contact_phone,
        coverLetter: values.cover_letter || undefined,
        generatedCvId:
          effectiveCvMode === 'GENERATED' ? (dernierCv?.id ?? undefined) : undefined,
        cvFile: effectiveCvMode === 'UPLOAD' ? (cvFile ?? undefined) : undefined,
      }),
    onSuccess: async () => {
      await invalidateApplications();
      // Le dossier ferme la boucle d'un match : la carte passe de « A
      // envoyer » au statut de la candidature. Sans cette invalidation,
      // l'onglet Matchs continuerait de reclamer un dossier deja parti.
      await queryClient.invalidateQueries({ queryKey: MATCHES_KEY });
      Alert.alert(
        'Candidature envoyée',
        "L'entreprise a été prévenue. Tu suivras sa réponse dans l'onglet Candidatures.",
        [{ text: 'OK', onPress: () => router.back() }],
      );
    },
    onError: (error) =>
      setErreurServeur(
        error instanceof ApiError
          ? error.message
          : "Impossible d'envoyer ta candidature.",
      ),
  });

  const choisirPdf = async () => {
    const file = await pickPdf();
    if (file) setCvFile(file);
  };

  const onSubmit = (values: FormValues) => {
    setErreurServeur(null);
    if (effectiveCvMode === 'GENERATED' && !dernierCv) {
      setErreurServeur(
        "Génère d'abord ton CV Jeuncy depuis ton profil, ou joins un PDF.",
      );

      return;
    }
    if (effectiveCvMode === 'UPLOAD' && !cvFile) {
      setErreurServeur('Choisis le PDF de ton CV.');

      return;
    }
    apply.mutate(values);
  };

  if (offer.isPending || profile.isPending || cvs.isPending) {
    return (
      <Screen hasHeader scroll={false} contentStyle={styles.centered}>
        <ActivityIndicator color={colors.accent} />
      </Screen>
    );
  }

  if (offer.isError) {
    return (
      <Screen hasHeader>
        <EmptyState
          title="Offre indisponible"
          description={offer.error.message}
          action={{ label: 'Retour', onPress: () => router.back() }}
        />
      </Screen>
    );
  }

  // Pas de profil : le serveur refuserait (requireProfile). Autant le dire
  // avant, avec l'issue.
  if (profile.data === null) {
    return (
      <Screen hasHeader>
        <EmptyState
          title="Crée d'abord ton profil"
          description="Ton prénom, ton nom et ta date de naissance suffisent. L'entreprise doit savoir qui postule."
          action={{
            label: 'Créer mon profil',
            onPress: () => router.push('/profil/informations'),
          }}
        />
      </Screen>
    );
  }

  const publisher = publisherOf(offer.data);
  const cvChoices = [
    ...(dernierCv
      ? [
          {
            value: 'GENERATED' as const,
            label: 'Mon CV Jeuncy',
            hint: `Dernière version, générée le ${formatDateFr(dernierCv.generated_at)}`,
          },
        ]
      : []),
    {
      value: 'UPLOAD' as const,
      label: 'Joindre un PDF',
      hint: cvFile ? cvFile.name : '5 Mo maximum',
    },
  ];

  return (
    <Screen hasHeader>
      <View style={styles.form}>
        <Card>
          <Text variant="sectionTitle">{offer.data.title}</Text>
          <Text variant="small" tone="muted">
            {publisher?.name}
            {offer.data.city ? ` · ${offer.data.city}` : ''}
          </Text>
        </Card>

        <Controller
          control={control}
          name="contact_phone"
          render={({ field: { onChange, onBlur, value } }) => (
            <Field
              label="Téléphone *"
              value={value}
              onChangeText={onChange}
              onBlur={onBlur}
              error={errors.contact_phone?.message}
              keyboardType="phone-pad"
              autoComplete="tel"
              textContentType="telephoneNumber"
              placeholder="Ex : 06 12 34 56 78"
            />
          )}
        />

        <ChoiceGroup<CvMode>
          label="CV joint *"
          choices={cvChoices}
          value={effectiveCvMode}
          onChange={setCvMode}
        />
        {effectiveCvMode === 'GENERATED' && dernierCv ? (
          <Button
            label="Voir ce CV"
            variant="ghost"
            onPress={() => void openPdf(dernierCv.file_url)}
          />
        ) : null}
        {effectiveCvMode === 'UPLOAD' ? (
          <Button
            label={cvFile ? 'Changer de fichier' : 'Choisir le PDF'}
            variant="secondary"
            onPress={() => void choisirPdf()}
          />
        ) : null}
        {!dernierCv ? (
          <Text variant="small" tone="muted">
            Pas encore de CV Jeuncy ? Tu peux en générer un depuis ton profil.
          </Text>
        ) : null}

        <Controller
          control={control}
          name="cover_letter"
          render={({ field: { onChange, onBlur, value } }) => (
            <Field
              label="Lettre de motivation (facultatif)"
              value={value}
              onChangeText={onChange}
              onBlur={onBlur}
              error={errors.cover_letter?.message}
              multiline
              numberOfLines={6}
              style={styles.multiline}
              placeholder="Explique en quelques lignes pourquoi cette offre t'intéresse…"
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
          label="Envoyer ma candidature"
          onPress={handleSubmit(onSubmit)}
          loading={apply.isPending}
        />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  centered: { alignItems: 'center', justifyContent: 'center' },
  form: { gap: spacing.lg },
  multiline: { minHeight: 140, textAlignVertical: 'top' },
});
