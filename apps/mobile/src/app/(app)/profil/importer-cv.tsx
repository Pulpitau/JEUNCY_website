import { useMutation } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, Alert, StyleSheet, View } from 'react-native';

import { pickPdf } from '@/components/features/profile/cv-section';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { EmptyState } from '@/components/ui/empty-state';
import { Screen } from '@/components/ui/screen';
import { Text } from '@/components/ui/text';
import { useCandidateProfile, useInvalidateProfile } from '@/hooks/use-candidate-profile';
import {
  addEducation,
  addExperience,
  addLanguage,
  importCv,
  syncSkills,
  syncSoftware,
  updateProfile,
  type CandidateProfile,
  type ImportedCvData,
} from '@/lib/api/candidate-profile';
import { ApiError } from '@/lib/api/client';
import { formatPeriodFr } from '@/lib/dates';
import { spacing } from '@/theme/typography';

// Niveau retenu quand le CV cite une langue sans preciser son niveau : « A2 »
// est une hypothese basse que le candidat corrigera plus facilement qu'une
// hypothese flatteuse (meme choix que le web).
const DEFAULT_LANGUAGE_LEVEL = 'A2';

// Une proposition lue dans le PDF, que le candidat garde ou ecarte.
interface Suggestion {
  key: string;
  label: string;
  detail?: string;
  apply: () => Promise<unknown>;
}

interface Groupe {
  titre: string;
  items: Suggestion[];
}

const sameName = (a: string, b: string) =>
  a.trim().toLowerCase() === b.trim().toLowerCase();

// Transforme la lecture du PDF en propositions concretes, en ecartant ce que
// le profil a deja : ce que le candidat a saisi lui-meme prime toujours sur ce
// qu'on a lu dans son fichier. Les entrees incompletes (experience sans
// entreprise ni date) ne sont pas proposees — mieux vaut que le candidat les
// saisisse lui-meme qu'une ligne bancale a corriger.
function buildGroups(data: ImportedCvData, profile: CandidateProfile): Groupe[] {
  const groupes: Groupe[] = [];

  const coordonnees: Suggestion[] = [];
  const info: [keyof ImportedCvData & keyof CandidateProfile, string][] = [
    ['phone', 'Téléphone'],
    ['postal_code', 'Code postal'],
    ['linkedin_url', 'LinkedIn'],
    ['driving_license', 'Permis'],
  ];
  for (const [champ, label] of info) {
    const value = data[champ];
    if (typeof value === 'string' && value && !profile[champ]) {
      coordonnees.push({
        key: `info:${champ}`,
        label,
        detail: value,
        apply: () => updateProfile({ [champ]: value }),
      });
    }
  }
  if (coordonnees.length) groupes.push({ titre: 'Coordonnées', items: coordonnees });

  const experiences = data.experiences
    .filter((e) => e.title && e.company && e.start_date)
    .filter(
      (e) =>
        !profile.experiences.some(
          (p) => sameName(p.title, e.title) && sameName(p.company, e.company ?? ''),
        ),
    )
    .map<Suggestion>((e, index) => ({
      key: `exp:${index}`,
      label: e.title,
      detail: `${e.company} · ${formatPeriodFr(e.start_date as string, e.end_date)}`,
      apply: () =>
        addExperience({
          title: e.title,
          company: e.company as string,
          start_date: e.start_date as string,
          end_date: e.end_date,
          description: e.description,
        }),
    }));
  if (experiences.length) groupes.push({ titre: 'Expériences', items: experiences });

  const educations = data.educations
    .filter((e) => e.degree && e.school && e.start_date)
    .filter((e) => !profile.educations.some((p) => sameName(p.degree, e.degree)))
    .map<Suggestion>((e, index) => ({
      key: `edu:${index}`,
      label: e.degree,
      detail: `${e.school} · ${formatPeriodFr(e.start_date as string, e.end_date)}`,
      apply: () =>
        addEducation({
          degree: e.degree,
          school: e.school as string,
          start_date: e.start_date as string,
          end_date: e.end_date,
        }),
    }));
  if (educations.length) groupes.push({ titre: 'Formations', items: educations });

  const languages = data.languages
    .filter((l) => !profile.languages.some((p) => sameName(p.name, l.name)))
    .map<Suggestion>((l, index) => ({
      key: `lang:${index}`,
      label: l.name,
      detail: l.level ?? `${DEFAULT_LANGUAGE_LEVEL} (niveau à vérifier)`,
      apply: () =>
        addLanguage({ name: l.name, level: l.level ?? DEFAULT_LANGUAGE_LEVEL }),
    }));
  if (languages.length) groupes.push({ titre: 'Langues', items: languages });

  // Competences et logiciels se synchronisent par liste entiere (PUT) : chaque
  // proposition est cochable, et l'application envoie l'union en un appel.
  const existingSkills = profile.skills.map((s) => s.name);
  const newSkills = data.skills.filter(
    (s) => !existingSkills.some((e) => sameName(e, s)),
  );
  if (newSkills.length) {
    groupes.push({
      titre: 'Compétences',
      items: newSkills.map((s) => ({
        key: `skill:${s}`,
        label: s,
        apply: () => Promise.resolve(),
      })),
    });
  }

  const existingSoftware = profile.software.map((s) => s.name);
  const newSoftware = data.software.filter(
    (s) => !existingSoftware.some((e) => sameName(e, s)),
  );
  if (newSoftware.length) {
    groupes.push({
      titre: 'Logiciels',
      items: newSoftware.map((s) => ({
        key: `soft:${s}`,
        label: s,
        apply: () => Promise.resolve(),
      })),
    });
  }

  return groupes;
}

export default function ImporterCvScreen() {
  const router = useRouter();
  const profile = useCandidateProfile();
  const invalidate = useInvalidateProfile();
  const [groupes, setGroupes] = useState<Groupe[] | null>(null);
  const [decoches, setDecoches] = useState<Set<string>>(new Set());

  const lecture = useMutation({
    mutationFn: importCv,
    onSuccess: (data) => {
      if (!profile.data) return;
      setGroupes(buildGroups(data, profile.data));
      setDecoches(new Set());
    },
  });

  const application = useMutation({
    mutationFn: async (retenues: Suggestion[]) => {
      if (!profile.data) return;
      // Competences et logiciels : union avec l'existant, un seul appel chacun.
      const skills = retenues
        .filter((s) => s.key.startsWith('skill:'))
        .map((s) => s.label);
      const software = retenues
        .filter((s) => s.key.startsWith('soft:'))
        .map((s) => s.label);
      if (skills.length)
        await syncSkills([...profile.data.skills.map((s) => s.name), ...skills]);
      if (software.length) {
        await syncSoftware([...profile.data.software.map((s) => s.name), ...software]);
      }
      // Le reste, dans l'ordre du CV : c'est l'ordre chronologique voulu par le
      // candidat, et celui dans lequel les rubriques s'afficheront.
      for (const s of retenues) {
        if (!s.key.startsWith('skill:') && !s.key.startsWith('soft:')) await s.apply();
      }
    },
    onSuccess: async (_, retenues) => {
      await invalidate();
      Alert.alert(
        'Profil complété',
        `${retenues.length} élément${retenues.length > 1 ? 's' : ''} ajouté${retenues.length > 1 ? 's' : ''} à ton profil. Relis-les, et corrige ce qui doit l'être.`,
        [{ text: 'OK', onPress: () => router.back() }],
      );
    },
    onError: (error) =>
      Alert.alert(
        'Enregistrement incomplet',
        error instanceof ApiError
          ? `Le CV a été lu, mais une partie n'a pas pu être enregistrée : ${error.message}`
          : "Le CV a été lu, mais une partie n'a pas pu être enregistrée. Réessaie dans un instant.",
      ),
  });

  const choisir = async () => {
    const file = await pickPdf();
    if (file) lecture.mutate(file);
  };

  const basculer = (key: string) =>
    setDecoches((prev) => {
      const next = new Set(prev);
      if (next.has(key)) next.delete(key);
      else next.add(key);

      return next;
    });

  if (profile.data === null) {
    return (
      <Screen hasHeader>
        <EmptyState
          title="Crée d'abord ton profil"
          description="Ton prénom, ton nom et ta date de naissance. Ensuite, ton CV remplira le reste."
          action={{
            label: 'Créer mon profil',
            onPress: () => router.replace('/profil/informations'),
          }}
        />
      </Screen>
    );
  }

  if (lecture.isPending) {
    return (
      <Screen hasHeader scroll={false} contentStyle={styles.centered}>
        <ActivityIndicator />
        <Text variant="body" tone="muted">
          Lecture de ton CV…
        </Text>
      </Screen>
    );
  }

  if (lecture.isError) {
    return (
      <Screen hasHeader>
        <EmptyState
          title="CV illisible"
          description={
            lecture.error instanceof ApiError
              ? lecture.error.message
              : "Ce PDF n'a pas pu être lu. Réessaie avec un autre fichier."
          }
          action={{ label: 'Choisir un autre PDF', onPress: () => void choisir() }}
        />
      </Screen>
    );
  }

  if (groupes === null) {
    return (
      <Screen hasHeader>
        <View style={styles.intro}>
          <Text variant="title">Remplir mon profil depuis mon CV</Text>
          <Text variant="body" tone="muted">
            Choisis ton CV en PDF. Jeuncy y lit tes expériences, formations, compétences,
            logiciels et langues, et te les propose. Tu gardes ce qui est juste, tu
            écartes le reste — rien n&apos;est ajouté sans ton accord.
          </Text>
          <Text variant="small" tone="muted">
            Les CV scannés (une image, sans texte sélectionnable) et les PDF protégés par
            mot de passe ne peuvent pas être lus.
          </Text>
          <Button label="Choisir mon CV (PDF)" onPress={() => void choisir()} />
        </View>
      </Screen>
    );
  }

  const total = groupes.reduce((n, g) => n + g.items.length, 0);
  const retenues = groupes.flatMap((g) => g.items).filter((s) => !decoches.has(s.key));

  if (total === 0) {
    return (
      <Screen hasHeader>
        <EmptyState
          title="Rien de nouveau à ajouter"
          description="Tout ce que Jeuncy a pu lire dans ce CV est déjà dans ton profil, ou n'était pas assez complet pour être proposé."
          action={{ label: 'Retour au profil', onPress: () => router.back() }}
        />
      </Screen>
    );
  }

  return (
    <Screen hasHeader>
      <View style={styles.review}>
        <Text variant="body" tone="muted">
          Voici ce que Jeuncy a lu dans ton CV. Décoche ce qui est faux ou que tu ne veux
          pas.
        </Text>

        {groupes.map((groupe) => (
          <Card key={groupe.titre}>
            <Text variant="sectionTitle">{groupe.titre}</Text>
            {groupe.items.map((item) => (
              <Checkbox
                key={item.key}
                label={item.detail ? `${item.label} — ${item.detail}` : item.label}
                checked={!decoches.has(item.key)}
                onChange={() => basculer(item.key)}
              />
            ))}
          </Card>
        ))}

        <Button
          label={
            retenues.length === 0
              ? 'Rien à ajouter'
              : `Ajouter à mon profil (${retenues.length})`
          }
          onPress={() => application.mutate(retenues)}
          loading={application.isPending}
          disabled={retenues.length === 0}
        />
        <Button
          label="Choisir un autre PDF"
          variant="ghost"
          onPress={() => void choisir()}
        />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  centered: { alignItems: 'center', justifyContent: 'center', gap: spacing.md },
  intro: { gap: spacing.lg },
  review: { gap: spacing.lg },
});
