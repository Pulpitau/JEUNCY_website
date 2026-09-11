import { UserRole } from '@jeuncy/shared';
import { StyleSheet, View } from 'react-native';

import { BrandHeader } from '@/components/brand-header';
import { ThemeSwitch } from '@/components/theme-switch';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Screen } from '@/components/ui/screen';
import { Text } from '@/components/ui/text';
import { logout } from '@/lib/api/auth';
import { useAuthStore } from '@/store/auth-store';
import { spacing } from '@/theme/typography';

// Onglet Profil (candidat) / Compte (autres roles). Provisoire : le profil
// complet arrive avec le lot B, la confidentialite et la suppression du
// compte avec le lot D. Le reglage d'apparence et la deconnexion sont deja
// a leur place definitive, en bas.
export default function ProfilScreen() {
  const user = useAuthStore((state) => state.user);

  if (!user) return null;

  const isCandidate = user.role === UserRole.CANDIDATE;

  return (
    <Screen>
      <BrandHeader
        title={isCandidate ? 'Mon profil' : 'Mon compte'}
        subtitle={user.email}
      />

      {isCandidate ? (
        <Card>
          <Text variant="sectionTitle">Bientôt ici</Text>
          <Text variant="small" tone="muted">
            Tes informations, expériences, formations, compétences, langues, ta photo et
            ton CV.
          </Text>
        </Card>
      ) : (
        <Card>
          <Text variant="sectionTitle">Espace entreprise et CFA</Text>
          <Text variant="small" tone="muted">
            La gestion des offres et des candidatures reçues arrive dans une prochaine
            version. En attendant, tout reste disponible sur jeuncy.com.
          </Text>
        </Card>
      )}

      <View style={styles.bas}>
        <ThemeSwitch />
        <Button
          label="Se déconnecter"
          variant="secondary"
          onPress={() => void logout()}
        />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  bas: { marginTop: spacing.xl },
});
