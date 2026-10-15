import { Stack, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';
import { WebView } from 'react-native-webview';

import { EmptyState } from '@/components/ui/empty-state';
import { downloadApplicationCv } from '@/lib/api/applications';
import { ApiError } from '@/lib/api/client';
import { useTheme } from '@/theme/theme-provider';
import { File, Paths } from 'expo-file-system';

// Apercu direct du CV d'un dossier recu, en remplacement de la feuille de
// partage iOS (qui ne montre qu'une miniature non tapable, retour du
// 2026-10-15). WKWebView sait afficher un PDF local sans reseau : on
// retelecharge le fichier deja garde par downloadApplicationCv plutot que de
// faire transiter l'URI file:// par la navigation.
export default function CvPreviewScreen() {
  const { colors } = useTheme();
  const { applicationId } = useLocalSearchParams<{ applicationId: string }>();
  const [uri, setUri] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    downloadApplicationCv(Number(applicationId))
      .then(({ bytes, filename }) => {
        if (cancelled) return;
        const file = new File(Paths.cache, filename ?? `CV-${applicationId}.pdf`);
        file.write(bytes);
        setUri(file.uri);
      })
      .catch((e: unknown) => {
        if (!cancelled) {
          setError(e instanceof ApiError ? e.message : 'Réessaie dans un instant.');
        }
      });

    return () => {
      cancelled = true;
    };
  }, [applicationId]);

  return (
    <View style={[styles.flex, { backgroundColor: colors.background }]}>
      <Stack.Screen options={{ title: 'CV' }} />
      {error ? (
        <EmptyState title="CV indisponible" description={error} />
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

const styles = StyleSheet.create({
  flex: { flex: 1 },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center' },
});
