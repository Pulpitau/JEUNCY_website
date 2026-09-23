import { UserRole } from '@jeuncy/shared';
import { useMutation } from '@tanstack/react-query';
import { File, Paths } from 'expo-file-system';
import * as Sharing from 'expo-sharing';
import * as WebBrowser from 'expo-web-browser';
import { useState } from 'react';
import { Alert, StyleSheet, Switch, View } from 'react-native';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Screen } from '@/components/ui/screen';
import { Text } from '@/components/ui/text';
import { useCandidateProfile, useInvalidateProfile } from '@/hooks/use-candidate-profile';
import { useDeviceLocation } from '@/hooks/use-device-location';
import { deleteAccount, exportAccountData } from '@/lib/api/account';
import { clearRefreshToken } from '@/lib/secure-store';
import {
  locationSourceOf,
  updatePreferences,
  updateProfile,
} from '@/lib/api/candidate-profile';
import { ApiError } from '@/lib/api/client';
import { useAuthStore } from '@/store/auth-store';
import { useTheme } from '@/theme/theme-provider';
import { spacing } from '@/theme/typography';

const SITE = 'https://jeuncy.com';

// Ecran « Confidentialite et donnees », equivalent de /mon-compte/confidentialite
// sur le site. Rien n'est reecrit ici : l'export, la suppression et le droit
// d'opposition a la CVtheque existent deja cote API (MOBILE.md section 9.1),
// l'application les rend accessibles.
//
// Les textes legaux (mentions, politique de confidentialite) s'ouvrent dans
// une feuille Safari integree — l'app ne quitte pas l'ecran — plutot que
// d'etre recopies ici : la politique a change le 2026-09-11, une copie serait
// perimee a la premiere evolution. Une seule source de verite, le site.
export default function ConfidentialiteScreen() {
  const { colors } = useTheme();
  const user = useAuthStore((state) => state.user);
  const clearSession = useAuthStore((state) => state.clearSession);
  const profile = useCandidateProfile();
  const invalidateProfile = useInvalidateProfile();
  const [confirmEmail, setConfirmEmail] = useState('');
  const isCandidate = user?.role === UserRole.CANDIDATE;

  const visibility = useMutation({
    mutationFn: (visible: boolean) => updateProfile({ is_visible_in_cvtheque: visible }),
    onSuccess: () => void invalidateProfile(),
    onError: () => Alert.alert('Réglage non enregistré', 'Réessaie dans un instant.'),
  });

  // Autorisation du portrait : même écran que la visibilité CVthèque, parce
  // que c'est la même question posée deux fois — qui voit quoi de moi.
  const photoVisible = useMutation({
    mutationFn: (visible: boolean) =>
      updatePreferences({ show_photo_to_employers: visible }),
    onSuccess: () => void invalidateProfile(),
    onError: () => Alert.alert('Réglage non enregistré', 'Réessaie dans un instant.'),
  });

  const { disable: disableLocation } = useDeviceLocation();
  const positionActive =
    profile.data !== null &&
    profile.data !== undefined &&
    locationSourceOf(profile.data) === 'DEVICE';

  // L'export est ecrit dans le cache puis propose par la feuille de partage
  // d'iOS : « Enregistrer dans Fichiers », AirDrop, mail... C'est le candidat
  // qui decide ou vont ses donnees, pas l'application.
  const exportation = useMutation({
    mutationFn: async () => {
      const data = await exportAccountData();
      const file = new File(
        Paths.cache,
        `jeuncy-mes-donnees-${new Date().toISOString().slice(0, 10)}.json`,
      );
      file.write(JSON.stringify(data, null, 2));
      if (!(await Sharing.isAvailableAsync())) {
        throw new ApiError(
          {
            code: 'SHARING_UNAVAILABLE',
            message: 'Le partage de fichier est indisponible sur cet appareil.',
          },
          0,
        );
      }
      await Sharing.shareAsync(file.uri, {
        mimeType: 'application/json',
        UTI: 'public.json',
      });
    },
    onError: (error) =>
      Alert.alert(
        'Export impossible',
        error instanceof ApiError ? error.message : 'Réessaie dans un instant.',
      ),
  });

  const suppression = useMutation({
    mutationFn: () => deleteAccount(confirmEmail.trim()),
    onSuccess: async () => {
      // Le compte n'existe plus cote serveur : on nettoie sans appeler
      // /auth/logout, qui repondrait 401.
      await clearRefreshToken();
      clearSession();
    },
    onError: (error) =>
      Alert.alert(
        'Suppression refusée',
        error instanceof ApiError ? error.message : 'Réessaie dans un instant.',
      ),
  });

  const emailMatches =
    confirmEmail.trim().toLowerCase() === (user?.email ?? '').toLowerCase();

  const confirmerSuppression = () => {
    Alert.alert(
      'Supprimer ton compte ?',
      'Ton profil, ton CV et tes candidatures seront effacés définitivement. Cette action est irréversible.',
      [
        { text: 'Annuler', style: 'cancel' },
        {
          text: 'Supprimer mon compte',
          style: 'destructive',
          onPress: () => suppression.mutate(),
        },
      ],
    );
  };

  return (
    <Screen hasHeader>
      <View style={styles.page}>
        {isCandidate && profile.data ? (
          <Card>
            <Text variant="sectionTitle">Visibilité dans la CVthèque</Text>
            <Text variant="small" tone="muted">
              Les entreprises et CFA inscrits sur Jeuncy peuvent consulter ton profil et
              ton CV dans la CVthèque. Tu peux t&apos;en retirer à tout moment : tu restes
              libre de postuler.
            </Text>
            <View style={styles.switchRow}>
              <Text variant="bodyStrong" style={styles.switchLabel}>
                Visible par les recruteurs
              </Text>
              <Switch
                value={profile.data.is_visible_in_cvtheque}
                onValueChange={(value) => visibility.mutate(value)}
                disabled={visibility.isPending}
                trackColor={{ true: colors.accent, false: colors.border }}
                accessibilityLabel="Visible dans la CVthèque"
              />
            </View>

            <View style={styles.switchRow}>
              <Text variant="bodyStrong" style={styles.switchLabel}>
                Montrer ma photo aux recruteurs
              </Text>
              <Switch
                value={profile.data.show_photo_to_employers}
                onValueChange={(value) => photoVisible.mutate(value)}
                disabled={photoVisible.isPending}
                trackColor={{ true: colors.accent, false: colors.border }}
                accessibilityLabel="Montrer ma photo aux recruteurs"
              />
            </View>
            <Text variant="small" tone="muted">
              Décoché par défaut. Ta photo reste sur ton CV dans tous les cas ; ceci ne
              concerne que la carte que les recruteurs voient avant ta candidature.
            </Text>
          </Card>
        ) : null}

        {/* Position GPS : toujours effaçable, et le dire ici plutôt que
            seulement dans les réglages de Découvrir. Une donnée qu'on ne sait
            pas retirer est une donnée qu'on n'aurait pas dû donner. */}
        {isCandidate && profile.data ? (
          <Card>
            <Text variant="sectionTitle">Ma position</Text>
            <Text variant="small" tone="muted">
              {positionActive
                ? 'Jeuncy utilise la position de ton téléphone pour classer les offres par distance. Elle est arrondie à environ un kilomètre, et les recruteurs ne la voient jamais.'
                : "Jeuncy utilise la commune de ton profil pour classer les offres par distance. Aucune position de téléphone n'est enregistrée."}
            </Text>
            {positionActive ? (
              <Button
                label="Effacer ma position"
                variant="secondary"
                loading={disableLocation.isPending}
                onPress={() =>
                  disableLocation.mutate(undefined, {
                    onError: (error: Error) =>
                      Alert.alert('Action impossible', error.message),
                  })
                }
              />
            ) : null}
          </Card>
        ) : null}

        <Card>
          <Text variant="sectionTitle">Mes données</Text>
          <Text variant="small" tone="muted">
            Récupère une copie de toutes les données que Jeuncy détient sur toi, au format
            JSON (droit à la portabilité).
          </Text>
          <Button
            label="Exporter mes données"
            variant="secondary"
            onPress={() => exportation.mutate()}
            loading={exportation.isPending}
          />
        </Card>

        <Card>
          <Text variant="sectionTitle">Textes légaux</Text>
          <Button
            label="Politique de confidentialité"
            variant="ghost"
            onPress={() => void WebBrowser.openBrowserAsync(`${SITE}/confidentialite`)}
          />
          <Button
            label="Mentions légales"
            variant="ghost"
            onPress={() => void WebBrowser.openBrowserAsync(`${SITE}/mentions-legales`)}
          />
        </Card>

        <Card>
          <Text variant="sectionTitle" tone="danger">
            Supprimer mon compte
          </Text>
          <Text variant="small" tone="muted">
            Ton compte, ton profil, ton CV et tes candidatures seront effacés. Si tu as
            déjà réglé un paiement, ton compte est anonymisé plutôt que supprimé : la loi
            impose de conserver les pièces comptables.
          </Text>
          <Field
            label={`Pour confirmer, saisis ton adresse email (${user?.email ?? ''})`}
            value={confirmEmail}
            onChangeText={setConfirmEmail}
            autoCapitalize="none"
            autoComplete="off"
            keyboardType="email-address"
            placeholder={user?.email}
          />
          <Button
            label="Supprimer définitivement mon compte"
            variant="secondary"
            onPress={confirmerSuppression}
            disabled={!emailMatches}
            loading={suppression.isPending}
          />
        </Card>
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  page: { gap: spacing.lg },
  switchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    marginTop: spacing.sm,
  },
  switchLabel: { flex: 1 },
});
