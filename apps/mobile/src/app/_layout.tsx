import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useFonts } from 'expo-font';
import { Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';
import { StyleSheet } from 'react-native';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { EmptyState } from '@/components/ui/empty-state';
import { Screen } from '@/components/ui/screen';
import { restoreSession, type RestoreOutcome } from '@/lib/api/auth';
import { useAuthStore } from '@/store/auth-store';
import { ThemeProvider, useTheme } from '@/theme/theme-provider';

// L'ecran de demarrage reste affiche tant que les polices ne sont pas chargees
// ET que la session n'est pas restauree. Sans cela, l'application montre un
// bref ecran "non connecte" avant de basculer sur l'accueil, ce qui donne
// l'impression d'un bug a chaque lancement.
void SplashScreen.preventAutoHideAsync();

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      // Les ecrans mobiles se remontent souvent (retours arriere, onglets) :
      // une minute de fraicheur evite de rappeler l'API a chaque affichage.
      staleTime: 60_000,
      retry: 1,
    },
  },
});

// Fin de session (deconnexion, suppression du compte, refresh refuse) : le
// cache part avec. Sinon, sur un telephone partage, le compte suivant verrait
// un instant le profil, les matchs ou les offres du precedent. Abonnement
// au store plutot qu'appel dans chaque chemin : tous passent par clearSession.
useAuthStore.subscribe((state, previous) => {
  if (previous.user && !state.user) queryClient.clear();
});

export default function RootLayout() {
  // Chaque graisse est chargee par son chemin exact, et non depuis la racine du
  // paquet (`import { Poppins_400Regular } from '@expo-google-fonts/poppins'`).
  // La difference n'est pas cosmetique : importer depuis la racine embarque les
  // 18 graisses de chaque famille, italiques comprises. Mesure sur l'export
  // iOS : 9,1 Mo d'assets contre moins de 1 Mo ici, pour exactement les memes
  // sept polices affichees.
  const [fontsLoaded] = useFonts({
    Poppins_400Regular: require('@expo-google-fonts/poppins/400Regular/Poppins_400Regular.ttf'),
    Poppins_500Medium: require('@expo-google-fonts/poppins/500Medium/Poppins_500Medium.ttf'),
    Poppins_600SemiBold: require('@expo-google-fonts/poppins/600SemiBold/Poppins_600SemiBold.ttf'),
    Poppins_700Bold: require('@expo-google-fonts/poppins/700Bold/Poppins_700Bold.ttf'),
    Inter_400Regular: require('@expo-google-fonts/inter/400Regular/Inter_400Regular.ttf'),
    Inter_500Medium: require('@expo-google-fonts/inter/500Medium/Inter_500Medium.ttf'),
    Inter_600SemiBold: require('@expo-google-fonts/inter/600SemiBold/Inter_600SemiBold.ttf'),
  });
  // null tant que la restauration n'a pas repondu.
  const [restoration, setRestoration] = useState<RestoreOutcome | null>(null);
  const sessionRestored = restoration !== null;

  useEffect(() => {
    void restoreSession().then(setRestoration);
  }, []);

  useEffect(() => {
    if (fontsLoaded && sessionRestored) {
      void SplashScreen.hideAsync();
    }
  }, [fontsLoaded, sessionRestored]);

  if (!fontsLoaded || !sessionRestored) {
    return null;
  }

  return (
    // GestureHandlerRootView a la racine, exige par react-native-gesture-handler
    // pour que les gestes natifs (la pile « Decouvrir ») recoivent les touches.
    // Sans lui, un Pan ne s'active jamais et l'app ne dit rien.
    <GestureHandlerRootView style={styles.root}>
      <QueryClientProvider client={queryClient}>
        <ThemeProvider>
          <SafeAreaProvider>
            {restoration === 'offline' ? (
              <OfflineScreen onRestored={setRestoration} />
            ) : (
              <NavigationGuard />
            )}
          </SafeAreaProvider>
        </ThemeProvider>
      </QueryClientProvider>
    </GestureHandlerRootView>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1 },
  centered: { flexGrow: 1, justifyContent: 'center' },
});

// Session impossible a restaurer faute de serveur (hors ligne, panne) : le
// jeton est encore dans le coffre. Envoyer sur l'ecran de connexion ferait
// croire a une session perdue ; on propose de reessayer a la place.
function OfflineScreen({
  onRestored,
}: {
  onRestored: (outcome: RestoreOutcome) => void;
}) {
  const { scheme } = useTheme();
  const [retrying, setRetrying] = useState(false);

  const retry = () => {
    if (retrying) return;
    setRetrying(true);
    void restoreSession().then((outcome) => {
      setRetrying(false);
      onRestored(outcome);
    });
  };

  return (
    <>
      <StatusBar style={scheme === 'dark' ? 'light' : 'dark'} />
      <Screen contentStyle={styles.centered}>
        <EmptyState
          title="Connexion impossible"
          description="On n'arrive pas à joindre Jeuncy. Vérifie ta connexion internet, puis réessaie : ta session est conservée."
          action={{ label: retrying ? 'Connexion…' : 'Réessayer', onPress: retry }}
        />
      </Screen>
    </>
  );
}

// Garde de navigation : equivalent natif du composant RequireAuth du web.
// Stack.Protected plutot qu'une redirection dans un effet : un ecran garde
// n'est jamais rendu, la ou l'effet laissait d'abord monter les onglets (et
// partir leurs requetes) une fraction de seconde avant de rediriger. Quand
// la garde bascule (connexion, deconnexion), le routeur ouvre le premier
// ecran redevenu accessible : l'accueil, ou l'ecran de connexion.
// Elle vit sous ThemeProvider pour que la barre d'etat suive le theme.
function NavigationGuard() {
  const { scheme, colors } = useTheme();
  const signedIn = useAuthStore((state) => state.user !== null);

  return (
    <>
      {/* Barre d'etat inversee par rapport au fond, sinon l'heure et la
          batterie deviennent illisibles au changement de theme. */}
      <StatusBar style={scheme === 'dark' ? 'light' : 'dark'} />
      <Stack
        screenOptions={{
          headerShown: false,
          contentStyle: { backgroundColor: colors.background },
          // Animation par defaut d'iOS : glissement lateral.
          animation: 'slide_from_right',
        }}
      >
        <Stack.Protected guard={signedIn}>
          <Stack.Screen name="(app)" />
        </Stack.Protected>
        {/* L'ordre compte : a la deconnexion, le routeur ouvre le premier
            ecran accessible, donc la connexion. */}
        <Stack.Protected guard={!signedIn}>
          <Stack.Screen name="(auth)/connexion" />
          <Stack.Screen name="(auth)/inscription" />
          <Stack.Screen name="(auth)/mot-de-passe-oublie" />
        </Stack.Protected>
      </Stack>
    </>
  );
}
