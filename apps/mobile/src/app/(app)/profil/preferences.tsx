import { ContractType, DrivingLicenseCategory, OfferSector } from '@jeuncy/shared';
import { useMutation } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DateField } from '@/components/ui/date-field';
import { EmptyState } from '@/components/ui/empty-state';
import { Field } from '@/components/ui/field';
import { MultiChip } from '@/components/ui/multi-chip';
import { Screen } from '@/components/ui/screen';
import { Section } from '@/components/ui/section';
import { Text } from '@/components/ui/text';
import { useCandidateProfile, useInvalidateProfile } from '@/hooks/use-candidate-profile';
import {
  updatePreferences,
  type CandidatePreferencesInput,
  type CandidateProfile,
} from '@/lib/api/candidate-profile';
import {
  CONTRACT_TYPE_LABELS,
  DRIVING_LICENSE_LABELS,
  OFFER_SECTOR_LABELS,
} from '@/lib/labels';
import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

// « Ce que je cherche » (MOBILE.md §3.1).
//
// Cet ecran fait deux choses a la fois, et c'est voulu : il aide le candidat
// a voir des offres qui lui parlent, et il le rend visible des employeurs a
// sa portee. Les deux rayons portent d'ailleurs des noms differents parce
// qu'ils ne servent pas a la meme chose :
//
//   - `search_radius_km` : jusqu'ou JE regarde les offres ;
//   - `mobility_radius_km` : jusqu'ou J'ACCEPTE d'aller travailler. C'est
//     celui-la que l'employeur voit, sous la forme « sa zone de mobilite
//     couvre ton offre » — jamais une distance, jamais une ville.
//
// TOUT EST FACULTATIF. Un profil muet reste eligible a tout : c'est le cas
// des profils anterieurs a cet ecran, et les exclure aurait vide le deck
// employeur le jour de son ouverture.

/** Paliers de rayon. Des valeurs rondes valent mieux qu'un curseur au pouce. */
const RADIUS_STEPS = [5, 10, 20, 30, 50, 100] as const;
const MAX_SECTORS = 3;
const MAX_PITCH = 160;

const CONTRACT_OPTIONS = Object.values(ContractType).map((value) => ({
  value,
  label: CONTRACT_TYPE_LABELS[value],
}));

const SECTOR_OPTIONS = Object.values(OfferSector).map((value) => ({
  value,
  label: OFFER_SECTOR_LABELS[value],
}));

const LICENSE_OPTIONS = Object.values(DrivingLicenseCategory).map((value) => ({
  value,
  // La lettre seule suffit sur une puce ; l'explication tient dans le libelle
  // long, qui ne rentrerait pas.
  label: DRIVING_LICENSE_LABELS[value].split(' — ')[0],
}));

const RADIUS_OPTIONS = RADIUS_STEPS.map((km) => ({
  value: String(km),
  label: `${km} km`,
}));

export default function PreferencesScreen() {
  const router = useRouter();
  const { data: profile, isPending, error } = useCandidateProfile();

  if (isPending) {
    return (
      <Screen hasHeader>
        <View style={styles.centered}>
          <ActivityIndicator />
        </View>
      </Screen>
    );
  }

  if (error || !profile) {
    return (
      <Screen hasHeader>
        <EmptyState
          title="Crée d'abord ton profil"
          description="Ton prénom, ton nom et ta date de naissance suffisent pour commencer."
          action={{
            label: 'Créer mon profil',
            onPress: () => router.replace('/profil/informations'),
          }}
        />
      </Screen>
    );
  }

  return <Formulaire profile={profile} />;
}

function Formulaire({ profile }: { profile: CandidateProfile }) {
  const router = useRouter();
  const { colors } = useTheme();
  const invalidateProfile = useInvalidateProfile();

  const [contrats, setContrats] = useState<ContractType[]>(
    profile.wanted_contract_types ?? [],
  );
  const [secteurs, setSecteurs] = useState<OfferSector[]>(profile.wanted_sectors ?? []);
  const [rayonRecherche, setRayonRecherche] = useState(profile.search_radius_km ?? 30);
  const [rayonMobilite, setRayonMobilite] = useState(profile.mobility_radius_km ?? 30);
  const [permis, setPermis] = useState(profile.has_driving_license);
  const [categories, setCategories] = useState<DrivingLicenseCategory[]>(
    profile.driving_license_categories ?? [],
  );
  const [vehicule, setVehicule] = useState(profile.has_vehicle);
  const [disponible, setDisponible] = useState<string | null>(profile.available_from);
  const [pitch, setPitch] = useState(profile.pitch ?? '');
  const [photoVisible, setPhotoVisible] = useState(profile.show_photo_to_employers);
  const [erreurServeur, setErreurServeur] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: (input: CandidatePreferencesInput) => updatePreferences(input),
    onSuccess: async () => {
      await invalidateProfile();
      router.back();
    },
    onError: (cause: Error) => setErreurServeur(cause.message),
  });

  const enregistrer = () => {
    setErreurServeur(null);
    mutation.mutate({
      wanted_contract_types: contrats,
      wanted_sectors: secteurs,
      search_radius_km: rayonRecherche,
      mobility_radius_km: rayonMobilite,
      has_driving_license: permis,
      // Sans permis, les categories n'ont plus de sens : les laisser
      // enverrait « pas de permis, catégorie B » a l'employeur.
      driving_license_categories: permis ? categories : [],
      has_vehicle: permis ? vehicule : false,
      available_from: disponible,
      pitch: pitch.trim() === '' ? null : pitch.trim(),
      show_photo_to_employers: photoVisible,
    });
  };

  return (
    <Screen hasHeader>
      <Text variant="hero">Ce que je cherche</Text>
      <Text variant="body" tone="muted" style={styles.intro}>
        Tout est facultatif. Plus tu en dis, plus les offres et les recruteurs qui
        t&apos;arrivent te correspondent.
      </Text>

      <Section title="Le poste">
        <MultiChip
          label="Types de contrat"
          options={CONTRACT_OPTIONS}
          value={contrats}
          onChange={setContrats}
          hint="Sans réponse, on te propose tous les contrats."
        />
        <MultiChip
          label="Secteurs"
          options={SECTOR_OPTIONS}
          value={secteurs}
          onChange={setSecteurs}
          max={MAX_SECTORS}
          hint="Trois au maximum : au-delà, ça ne cherche plus rien."
        />
        <DateField
          label="Disponible à partir du"
          value={disponible}
          onChange={setDisponible}
          minimumDate={new Date()}
          clearable
          clearLabel="Dès maintenant"
        />
      </Section>

      <Section title="Où je peux aller">
        <RadiusPicker
          label="Jusqu'où je regarde les offres"
          value={rayonRecherche}
          onChange={setRayonRecherche}
        />
        <RadiusPicker
          label="Jusqu'où j'accepte d'aller travailler"
          value={rayonMobilite}
          onChange={setRayonMobilite}
          hint="C'est ce rayon que les recruteurs utilisent. Ils ne voient jamais ta ville ni la distance — seulement si ta zone couvre leur offre."
        />
      </Section>

      <Section title="Mes déplacements">
        <Checkbox label="J'ai le permis" checked={permis} onChange={setPermis} />
        {permis ? (
          <>
            <MultiChip
              label="Catégories"
              options={LICENSE_OPTIONS}
              value={categories}
              onChange={setCategories}
            />
            <Checkbox
              label="J'ai un véhicule"
              checked={vehicule}
              onChange={setVehicule}
            />
          </>
        ) : null}
      </Section>

      <Section title="Ma phrase">
        <Field
          label={`En une phrase (${pitch.length}/${MAX_PITCH})`}
          value={pitch}
          onChangeText={(text) => setPitch(text.slice(0, MAX_PITCH))}
          placeholder="Motivé, disponible dès septembre, prêt à me déplacer."
          multiline
          numberOfLines={3}
          maxLength={MAX_PITCH}
        />
        <Text variant="small" tone="muted">
          Pas de téléphone ni d&apos;email ici : tes coordonnées partent avec ton dossier,
          quand tu décides de l&apos;envoyer.
        </Text>
      </Section>

      <Section title="Ma photo">
        <Checkbox
          label="Montrer ma photo aux recruteurs"
          checked={photoVisible}
          onChange={setPhotoVisible}
        />
        <Text variant="small" tone="muted">
          Décoché par défaut. Ta photo reste sur ton CV dans tous les cas ; ceci ne
          concerne que la carte que les recruteurs voient avant ta candidature.
        </Text>
      </Section>

      {erreurServeur ? (
        <View style={[styles.erreur, { borderColor: colors.danger }]}>
          <Text variant="small" tone="danger" accessibilityLiveRegion="polite">
            {erreurServeur}
          </Text>
        </View>
      ) : null}

      <Button
        label="Enregistrer"
        onPress={enregistrer}
        loading={mutation.isPending}
        style={styles.submit}
      />
    </Screen>
  );
}

/**
 * Paliers de rayon.
 *
 * Six valeurs rondes plutot qu'un curseur : au pouce, un curseur donne 37 km
 * quand on visait 30, et personne ne sait ce que valent ces sept kilometres.
 */
function RadiusPicker({
  label,
  value,
  onChange,
  hint,
}: {
  label: string;
  value: number;
  onChange: (km: number) => void;
  hint?: string;
}) {
  return (
    <MultiChip
      label={label}
      options={RADIUS_OPTIONS}
      value={[String(value)]}
      onChange={(choisis) => {
        // Un seul palier a la fois : on garde celui qui vient d'etre touche,
        // et retoucher le palier actif ne le decoche pas (un rayon nul ne
        // veut rien dire).
        const suivant = choisis.find((km) => km !== String(value));
        if (suivant) onChange(Number(suivant));
      }}
      hint={hint}
    />
  );
}

const styles = StyleSheet.create({
  centered: { paddingVertical: spacing.xxl, alignItems: 'center' },
  intro: { marginTop: spacing.xs },
  erreur: {
    marginTop: spacing.lg,
    padding: spacing.md,
    borderRadius: radii.md,
    borderWidth: 1,
  },
  submit: { marginTop: spacing.xl },
});
