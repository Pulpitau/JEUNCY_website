import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useFonts } from 'expo-font';
import { Stack, useRouter, useSegments } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { restoreSession } from '@/lib/api/auth';
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
  const [sessionRestored, setSessionRestored] = useState(false);

  useEffect(() => {
    restoreSession().finally(() => setSessionRestored(true));
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
    <QueryClientProvider client={queryClient}>
      <ThemeProvider>
        <SafeAreaProvider>
          <NavigationGuard />
        </SafeAreaProvider>
      </ThemeProvider>
    </QueryClientProvider>
  );
}

// Garde de navigation : equivalent natif du composant RequireAuth du web.
// Elle vit sous ThemeProvider pour que la barre d'etat suive le theme.
function NavigationGuard() {
  const { scheme, colors } = useTheme();
  const user = useAuthStore((state) => state.user);
  const segments = useSegments();
  const router = useRouter();

  const inAuthGroup = segments[0] === '(auth)';

  useEffect(() => {
    if (!user && !inAuthGroup) {
      router.replace('/connexion');
    } else if (user && inAuthGroup) {
      router.replace('/');
    }
  }, [user, inAuthGroup, router]);

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
      />
    </>
  );
}
