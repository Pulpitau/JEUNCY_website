import { Stack, useLocalSearchParams } from 'expo-router';
import { File, Paths } from 'expo-file-system';
import * as Sharing from 'expo-sharing';
import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Platform, StyleSheet, View } from 'react-native';
import { WebView } from 'react-native-webview';

import { EmptyState } from '@/components/ui/empty-state';
import { downloadApplicationCv } from '@/lib/api/applications';
import { ApiError } from '@/lib/api/client';
import { useTheme } from '@/theme/theme-provider';
import { spacing } from '@/theme/typography';

// Apercu direct du CV d'un dossier recu, en remplacement de la feuille de
// partage iOS (qui ne montre qu'une miniature non tapable, retour du
// 2026-10-15). WKWebView sait afficher un PDF local sans reseau : on
// retelecharge le fichier deja garde par downloadApplicationCv plutot que de
// faire transiter l'URI file:// par la navigation.
//
// ANDROID N'A PAS CE LECTEUR : sa WebView n'affiche pas les PDF (ecran
// blanc). Le fichier y est confie a la feuille de partage, qui laisse
// choisir un lecteur PDF installe.
//
// Le fichier est efface du cache en quittant l'ecran : c'est le CV d'un
// candidat, parfois mineur, et il n'a pas a survivre a sa lecture ni a une
// deconnexion sur un telephone partage.

const ANDROID = Platform.OS === 'android';

export default function CvPreviewScreen() {
  const { colors } = useTheme();
  const { applicationId } = useLocalSearchParams<{ applicationId: string }>();
  const [uri, setUri] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  // Incremente par « Réessayer » : relance le telechargement.
  const [attempt, setAttempt] = useState(0);
  const file = useRef<File | null>(null);

  useEffect(() => {
    let cancelled = false;
    downloadApplicationCv(Number(applicationId))
      .then(({ bytes, filename }) => {
        if (cancelled) return;
        const pdf = new File(Paths.cache, filename ?? `CV-${applicationId}.pdf`);
        pdf.write(bytes);
        file.current = pdf;
        setUri(pdf.uri);
        if (ANDROID) void ouvrirSurAndroid(pdf.uri);
      })
      .catch((e: unknown) => {
        if (!cancelled) {
          setError(
            e instanceof ApiError ? e.message : 'Vérifie ta connexion, puis réessaie.',
          );
        }
      });

    return () => {
      cancelled = true;
    };
  }, [applicationId, attempt]);

  // Nettoyage au demontage seulement : pendant que l'ecran est ouvert, le
  // lecteur PDF d'Android peut encore lire le fichier.
  useEffect(
    () => () => {
      try {
        if (file.current?.exists) file.current.delete();
      } catch {
        // Le cache sera vide par le systeme : rien a signaler a l'employeur.
      }
    },
    [],
  );

  const retry = () => {
    setError(null);
    setUri(null);
    setAttempt((n) => n + 1);
  };

  return (
    <View style={[styles.flex, { backgroundColor: colors.background }]}>
      <Stack.Screen options={{ title: 'CV' }} />
      {error ? (
        <View style={styles.centered}>
          <EmptyState
            title="CV indisponible"
            description={error}
            action={{ label: 'Réessayer', onPress: retry }}
          />
        </View>
      ) : uri && ANDROID ? (
        <View style={styles.centered}>
          <EmptyState
            title="CV prêt"
            description="Choisis une application pour lire le PDF."
            action={{ label: 'Ouvrir le CV', onPress: () => void ouvrirSurAndroid(uri) }}
          />
        </View>
      ) : uri ? (
        <WebView source={{ uri }} style={styles.flex} originWhitelist={['file://*']} />
      ) : (
        <View style={styles.centered}>
          <ActivityIndicator color={colors.accent} />
        </View>
      )}
    </View>
  );
}

async function ouvrirSurAndroid(uri: string) {
  try {
    await Sharing.shareAsync(uri, {
      mimeType: 'application/pdf',
      dialogTitle: 'Ouvrir le CV',
    });
  } catch {
    // Feuille fermee ou aucun lecteur : le bouton « Ouvrir le CV » reste la.
  }
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: spacing.xl,
  },
});
