import { UserRole } from '@jeuncy/shared';
import { StyleSheet, View } from 'react-native';

import { BrandHeader } from '@/components/brand-header';
import { ThemeSwitch } from '@/components/theme-switch';
import { Button } from '@/components/ui/button';
import { Screen } from '@/components/ui/screen';
import { Text } from '@/components/ui/text';
import { logout } from '@/lib/api/auth';
import { useAuthStore } from '@/store/auth-store';
import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

// Ce qui attend chaque role dans les phases suivantes (MOBILE.md section 6).
// L'ecran sert pour l'instant de preuve que la session tient et que le role
// remonte correctement depuis l'API.
const PROCHAINES_ETAPES: Record<string, { titre: string; items: string[] }> = {
  [UserRole.CANDIDATE]: {
    titre: 'Espace candidat',
    items: [
      'Mon profil et mon CV',
      "Recherche d'offres",
      'Mes candidatures',
      'Notifications',
    ],
  },
  [UserRole.COMPANY]: {
    titre: 'Espace entreprise',
    items: ["Profil de l'organisation", 'Mes offres', 'Candidatures reçues', 'CVthèque'],
  },
  [UserRole.CFA]: {
    titre: 'Espace CFA',
    items: ["Profil de l'organisation", 'Mes offres', 'Candidatures reçues', 'CVthèque'],
  },
};

export default function AccueilScreen() {
  const user = useAuthStore((state) => state.user);
  const { colors } = useTheme();

  if (!user) return null;

  // ADMIN et STAFF restent sur le web (MOBILE.md section 2) : plutot que de
  // les laisser sur un ecran vide, on le dit franchement.
  const espace = PROCHAINES_ETAPES[user.role];

  return (
    <Screen>
      <BrandHeader title={espace?.titre ?? 'Bienvenue'} subtitle={user.email} />

      <View
        style={[
          styles.carte,
          { backgroundColor: colors.surface, borderColor: colors.border },
        ]}
      >
        <Text variant="sectionTitle">Session active</Text>
        <Text variant="small" tone="muted">
          Ferme complètement l&apos;application et rouvre-la : tu dois rester connecté. Le
          jeton de session est rangé dans le coffre sécurisé du téléphone.
        </Text>
      </View>

      {espace ? (
        <View style={styles.section}>
          <Text variant="sectionTitle">Bientôt disponible ici</Text>
          {espace.items.map((item) => (
            <Text key={item} variant="body" tone="muted">
              • {item}
            </Text>
          ))}
        </View>
      ) : (
        <View style={styles.section}>
          <Text variant="body" tone="muted">
            L&apos;administration reste sur le site web : elle n&apos;a pas d&apos;usage
            sur mobile.
          </Text>
        </View>
      )}

      <ThemeSwitch />

      <Button label="Se déconnecter" variant="secondary" onPress={() => void logout()} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  carte: {
    padding: spacing.lg,
    borderRadius: radii.lg,
    borderWidth: 1,
    gap: spacing.sm,
  },
  section: {
    marginTop: spacing.xl,
    gap: spacing.sm,
  },
});
