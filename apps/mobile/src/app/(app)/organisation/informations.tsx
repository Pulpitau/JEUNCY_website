import { zodResolver } from '@hookform/resolvers/zod';
import { UserRole, WorkMode } from '@jeuncy/shared';
import { useMutation } from '@tanstack/react-query';
import { Stack, useRouter } from 'expo-router';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { StyleSheet, View } from 'react-native';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import { ChoiceGroup } from '@/components/ui/choice-group';
import { Field } from '@/components/ui/field';
import { Screen } from '@/components/ui/screen';
import { SelectField } from '@/components/ui/select-field';
import { Text } from '@/components/ui/text';
import {
  useInvalidateOrganization,
  useOrganization,
  useOrganizationRole,
} from '@/hooks/use-organization';
import { ApiError } from '@/lib/api/client';
import {
  createOrganization,
  updateOrganization,
  type CfaOrganization,
  type CfaOrganizationInput,
  type Company,
  type CompanyInput,
} from '@/lib/api/organization';
import { WORK_MODE_LABELS } from '@/lib/labels';
import { DIPLOMA_LEVEL_SELECT } from '@/lib/options';
import { spacing } from '@/theme/typography';

const optionalText = (max: number) =>
  z
    .string()
    .trim()
    .max(max, `${max} caractères maximum.`)
    .transform((v) => v || null);

const optionalUrl = z
  .string()
  .trim()
  .max(255)
  .refine((v) => v === '' || /^https?:\/\/\S+$/i.test(v), {
    message: 'Adresse invalide : elle doit commencer par https://',
  })
  .transform((v) => v || null);

// Un seul schema pour les deux roles : les champs propres au CFA restent vides
// pour une entreprise et ne sont pas envoyes (voir toPayload). Le SIRET est
// facultatif mais, s'il est saisi, doit faire exactement 14 chiffres
// (StoreCompanyRequest / StoreCfaOrganizationRequest : size:14).
const schema = z.object({
  name: z.string().trim().min(1, 'Renseigne le nom.').max(255),
  siret: z
    .string()
    .trim()
    .refine((v) => v === '' || /^\d{14}$/.test(v), {
      message: 'Le SIRET fait 14 chiffres.',
    })
    .transform((v) => v || null),
  description: optionalText(2000),
  website: optionalUrl,
  address: optionalText(255),
  city: optionalText(255),
  postal_code: z
    .string()
    .trim()
    .max(10)
    .regex(/^[0-9]*$/, 'Chiffres uniquement.')
    .transform((v) => v || null),
  work_mode: z.nativeEnum(WorkMode).nullable(),
  // CFA uniquement
  nda_number: optionalText(50),
  qualiopi_number: optionalText(50),
  diplomas_offered: optionalText(2000),
  diploma_level: z.string().nullable(),
});

type FormValues = z.input<typeof schema>;
type Parsed = z.output<typeof schema>;

const WORK_MODE_CHOICES = (Object.keys(WORK_MODE_LABELS) as WorkMode[]).map((value) => ({
  value,
  label: WORK_MODE_LABELS[value],
}));

function toPayload(role: typeof UserRole.COMPANY | typeof UserRole.CFA, v: Parsed) {
  const commun = {
    name: v.name,
    siret: v.siret,
    description: v.description,
    website: v.website,
    address: v.address,
    city: v.city,
    postal_code: v.postal_code,
  };
  if (role === UserRole.COMPANY) {
    return { ...commun, work_mode: v.work_mode } satisfies CompanyInput;
  }

  return {
    ...commun,
    training_mode: v.work_mode,
    nda_number: v.nda_number,
    qualiopi_number: v.qualiopi_number,
    diplomas_offered: v.diplomas_offered,
    diploma_level: v.diploma_level,
  } satisfies CfaOrganizationInput;
}

export default function OrganisationInformationsScreen() {
  const router = useRouter();
  const role = useOrganizationRole();
  const organization = useOrganization();
  const invalidate = useInvalidateOrganization();
  const [erreurServeur, setErreurServeur] = useState<string | null>(null);

  const isCfa = role === UserRole.CFA;
  const existing = organization.data ?? null;
  const cfa =
    existing && 'training_mode' in existing ? (existing as CfaOrganization) : null;
  const company = existing && 'work_mode' in existing ? (existing as Company) : null;

  const {
    control,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues, unknown, Parsed>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: existing?.name ?? '',
      siret: existing?.siret ?? '',
      description: existing?.description ?? '',
      website: existing?.website ?? '',
      address: existing?.address ?? '',
      city: existing?.city ?? '',
      postal_code: existing?.postal_code ?? '',
      work_mode: company?.work_mode ?? cfa?.training_mode ?? null,
      nda_number: cfa?.nda_number ?? '',
      qualiopi_number: cfa?.qualiopi_number ?? '',
      diplomas_offered: cfa?.diplomas_offered ?? '',
      diploma_level: cfa?.diploma_level ?? null,
    },
  });

  const save = useMutation({
    mutationFn: (values: Parsed) => {
      if (!role) throw new Error('Rôle inattendu');
      const payload = toPayload(role, values);

      return existing
        ? updateOrganization(role, payload)
        : createOrganization(role, payload);
    },
    onSuccess: async () => {
      await invalidate();
      router.back();
    },
    onError: (error) =>
      setErreurServeur(
        error instanceof ApiError ? error.message : 'Une erreur est survenue.',
      ),
  });

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
          value={typeof value === 'string' ? value : ''}
          onChangeText={onChange}
          onBlur={onBlur}
          error={errors[name]?.message}
          {...props}
        />
      )}
    />
  );

  return (
    <>
      <Stack.Screen options={{ title: isCfa ? 'Mon CFA' : 'Mon entreprise' }} />
      <Screen hasHeader>
        <View style={styles.form}>
          {champ('name', isCfa ? 'Nom du CFA *' : "Nom de l'entreprise *", {
            autoCapitalize: 'words',
          })}
          {champ('description', 'Description', {
            multiline: true,
            numberOfLines: 5,
            style: styles.multiline,
            placeholder: isCfa
              ? 'Ce que propose ton CFA, en quelques lignes.'
              : 'Ton activité, ton équipe, ce que tu proposes aux alternants.',
          })}
          {isCfa
            ? champ('diplomas_offered', 'Diplômes et formations proposés', {
                multiline: true,
                numberOfLines: 4,
                style: styles.multilineShort,
                placeholder: 'Ex : CAP Cuisine, Bac Pro Commerce, BTS MCO…',
              })
            : null}
          {isCfa ? (
            <Controller
              control={control}
              name="diploma_level"
              render={({ field: { onChange, value } }) => (
                <SelectField
                  label="Niveau de diplôme principal"
                  options={DIPLOMA_LEVEL_SELECT}
                  value={value ?? null}
                  onChange={onChange}
                  clearable
                />
              )}
            />
          ) : null}
          <Controller
            control={control}
            name="work_mode"
            render={({ field: { onChange, value } }) => (
              <ChoiceGroup<WorkMode>
                label={isCfa ? 'Type de formation' : 'Mode de travail'}
                choices={WORK_MODE_CHOICES}
                value={value ?? null}
                onChange={onChange}
              />
            )}
          />

          <Text variant="sectionTitle" style={styles.sectionTitle}>
            Coordonnées
          </Text>
          {champ('address', 'Adresse')}
          {champ('city', 'Ville', { autoCapitalize: 'words' })}
          {champ('postal_code', 'Code postal', { keyboardType: 'number-pad' })}
          {champ('website', 'Site web', {
            keyboardType: 'url',
            autoCapitalize: 'none',
            placeholder: 'https://…',
          })}

          <Text variant="sectionTitle" style={styles.sectionTitle}>
            Informations légales
          </Text>
          {champ('siret', 'SIRET', {
            keyboardType: 'number-pad',
            placeholder: '14 chiffres',
            maxLength: 14,
          })}
          {isCfa ? champ('nda_number', 'Numéro NDA') : null}
          {isCfa ? champ('qualiopi_number', 'Numéro de certification QUALIOPI') : null}

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
            label={existing ? 'Enregistrer' : 'Créer ma fiche'}
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
  sectionTitle: { marginTop: spacing.md },
  multiline: { minHeight: 120, textAlignVertical: 'top' },
  multilineShort: { minHeight: 90, textAlignVertical: 'top' },
});
