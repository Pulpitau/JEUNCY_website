import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import * as DocumentPicker from 'expo-document-picker';
import * as WebBrowser from 'expo-web-browser';
import { Alert, StyleSheet, View } from 'react-native';

import { Button } from '@/components/ui/button';
import { ItemRow, Section, SectionEmpty } from '@/components/ui/section';
import { Text } from '@/components/ui/text';
import { useInvalidateProfile } from '@/hooks/use-candidate-profile';
import {
  generateCv,
  listGeneratedCvs,
  removeOwnCv,
  uploadOwnCv,
  type CandidateProfile,
} from '@/lib/api/candidate-profile';
import { ApiError } from '@/lib/api/client';
import { formatDateFr } from '@/lib/dates';
import { spacing } from '@/theme/typography';

export const GENERATED_CVS_KEY = ['candidate-profile', 'cv'] as const;

// Limite du serveur (UploadCvRequest / StoreApplicationRequest : max:5120).
export const MAX_CV_BYTES = 5 * 1024 * 1024;

export function useGeneratedCvs(enabled = true) {
  return useQuery({ queryKey: GENERATED_CVS_KEY, queryFn: listGeneratedCvs, enabled });
}

// Ouvre un PDF dans le navigateur integre (feuille Safari sur iOS), qui sait
// l'afficher et le partager. Le telecharger dans l'app n'apporterait rien.
export function openPdf(url: string) {
  return WebBrowser.openBrowserAsync(url);
}

// Choisit un PDF sur le telephone (Fichiers, iCloud, Drive...). Renvoie null
// si le candidat annule ou si le fichier ne convient pas — l'erreur est deja
// affichee dans ce cas.
export async function pickPdf() {
  const result = await DocumentPicker.getDocumentAsync({
    type: 'application/pdf',
    copyToCacheDirectory: true,
    multiple: false,
  });
  if (result.canceled) return null;

  const asset = result.assets[0];
  if (asset.mimeType && asset.mimeType !== 'application/pdf') {
    Alert.alert('Fichier refusé', 'Seuls les fichiers PDF sont acceptés.');

    return null;
  }
  if (asset.size !== undefined && asset.size > MAX_CV_BYTES) {
    Alert.alert('Fichier trop lourd', 'Le CV ne doit pas dépasser 5 Mo.');

    return null;
  }

  return { uri: asset.uri, name: asset.name, type: 'application/pdf' };
}

// Deux sections du profil : le CV depose par le candidat (celui qu'il a
// choisi, propose en priorite aux recruteurs) et les CV generes par Jeuncy a
// partir du profil.
export function CvSection({ profile }: { profile: CandidateProfile }) {
  const invalidate = useInvalidateProfile();
  const queryClient = useQueryClient();
  const cvs = useGeneratedCvs();

  const erreur = (titre: string) => (error: unknown) =>
    Alert.alert(
      titre,
      error instanceof ApiError ? error.message : 'Réessaie dans un instant.',
    );

  const upload = useMutation({
    mutationFn: uploadOwnCv,
    onSuccess: () => void invalidate(),
    onError: erreur('CV non enregistré'),
  });

  const remove = useMutation({
    mutationFn: removeOwnCv,
    onSuccess: () => void invalidate(),
    onError: erreur('Suppression impossible'),
  });

  const generate = useMutation({
    mutationFn: generateCv,
    onSuccess: async (cv) => {
      await queryClient.invalidateQueries({ queryKey: GENERATED_CVS_KEY });
      // Le PDF s'ouvre aussitot : le candidat voit ce qu'il vient de creer.
      await openPdf(cv.file_url);
    },
    onError: erreur('Génération impossible'),
  });

  const deposer = async () => {
    const file = await pickPdf();
    if (file) upload.mutate(file);
  };

  const confirmerRetrait = () => {
    Alert.alert(
      'Supprimer ton CV ?',
      'Les recruteurs ne le verront plus dans la CVthèque.',
      [
        { text: 'Annuler', style: 'cancel' },
        { text: 'Supprimer', style: 'destructive', onPress: () => remove.mutate() },
      ],
    );
  };

  // Un CV archive n'a plus de fichier sur le serveur (CvService::archive,
  // conservation limitee a 3 ans) : inutile de le proposer.
  const utilisables = (cvs.data ?? []).filter((cv) => !cv.archived_at);

  return (
    <>
      <Section
        title="Mon CV"
        action={
          profile.cv_file_url
            ? { label: 'Remplacer', onPress: () => void deposer() }
            : { label: 'Déposer', onPress: () => void deposer() }
        }
      >
        {profile.cv_file_url ? (
          <ItemRow
            title={profile.cv_original_filename ?? 'Mon CV.pdf'}
            subtitle={
              profile.cv_uploaded_at
                ? `Déposé le ${formatDateFr(profile.cv_uploaded_at)}`
                : null
            }
            onPress={() => void openPdf(profile.cv_file_url as string)}
            onDelete={confirmerRetrait}
          />
        ) : (
          <SectionEmpty>
            Ton CV en PDF, tel que tu l&apos;as fait. C&apos;est lui que les recruteurs
            verront en premier.
          </SectionEmpty>
        )}
        {upload.isPending ? (
          <Text variant="small" tone="muted">
            Envoi en cours…
          </Text>
        ) : null}
      </Section>

      <Section title="CV Jeuncy">
        <Text variant="small" tone="muted">
          Un CV mis en page automatiquement à partir de ton profil. Regénère-le après
          chaque modification importante.
        </Text>
        <Button
          label={utilisables.length ? 'Regénérer mon CV' : 'Générer mon CV'}
          variant="secondary"
          onPress={() => generate.mutate()}
          loading={generate.isPending}
        />
        {utilisables.length > 0 ? (
          <View style={styles.list}>
            {utilisables.map((cv, index) => (
              <ItemRow
                key={cv.id}
                title={
                  index === 0
                    ? 'Dernière version'
                    : `Version du ${formatDateFr(cv.generated_at)}`
                }
                subtitle={
                  index === 0 ? `Générée le ${formatDateFr(cv.generated_at)}` : null
                }
                onPress={() => void openPdf(cv.file_url)}
              />
            ))}
          </View>
        ) : null}
      </Section>
    </>
  );
}

const styles = StyleSheet.create({
  list: { gap: spacing.sm },
});
