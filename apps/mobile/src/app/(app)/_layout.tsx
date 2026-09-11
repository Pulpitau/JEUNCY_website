import { Stack } from 'expo-router';

import { useTheme } from '@/theme/theme-provider';
import { fonts } from '@/theme/typography';

// Pile de l'espace connecte : les onglets en bas, et par-dessus les ecrans
// de detail (une offre, plus tard une candidature) qui glissent depuis la
// droite avec un bouton retour. Les onglets n'ont pas d'en-tete — chaque
// onglet gere le sien — mais les ecrans de detail utilisent l'en-tete natif,
// qui donne gratuitement le geste de retour par balayage sur iOS.
export default function AppLayout() {
  const { colors } = useTheme();

  return (
    <Stack
      screenOptions={{
        headerStyle: { backgroundColor: colors.background },
        headerTintColor: colors.accent,
        headerTitleStyle: { fontFamily: fonts.displaySemiBold, color: colors.text },
        headerShadowVisible: false,
        headerBackButtonDisplayMode: 'minimal',
        contentStyle: { backgroundColor: colors.background },
      }}
    >
      <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
      <Stack.Screen name="offres/[id]" options={{ title: 'Offre' }} />
    </Stack>
  );
}
