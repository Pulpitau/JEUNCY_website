import { UserRole } from '@jeuncy/shared';
import { useRouter } from 'expo-router';
import { StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Text } from '@/components/ui/text';
import { useMyApplications } from '@/hooks/use-my-applications';
import { APPLICATION_STATUS_LABELS } from '@/lib/labels';
import { useAuthStore } from '@/store/auth-store';
import { useTheme } from '@/theme/theme-provider';
import { spacing } from '@/theme/typography';

import { statusTone } from './status';

// Barre fixe en bas du detail d'une offre. Trois etats : pas candidat (rien),
// deja postule (le statut, sans bouton), ou le bouton « Postuler ». La
// candidature elle-meme se fait sur son propre ecran : un formulaire avec
// clavier ne se remplit pas confortablement au fond d'une page qui defile.
export function ApplyBar({ offerId }: { offerId: number }) {
  const router = useRouter();
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();
  const role = useAuthStore((state) => state.user?.role);
  const isCandidate = role === UserRole.CANDIDATE;
  const applications = useMyApplications(isCandidate);

  if (!isCandidate) return null;

  const existing = applications.data?.find((a) => a.job_offer_id === offerId);

  return (
    <View
      style={[
        styles.bar,
        {
          paddingBottom: insets.bottom + spacing.md,
          backgroundColor: colors.surface,
          borderTopColor: colors.border,
        },
      ]}
    >
      {existing ? (
        <View style={styles.status}>
          <Text variant="bodyStrong">Tu as déjà postulé</Text>
          <Badge
            label={APPLICATION_STATUS_LABELS[existing.status]}
            tone={statusTone(existing.status)}
          />
        </View>
      ) : (
        <Button
          label="Postuler"
          onPress={() =>
            router.push({
              pathname: '/offres/[id]/postuler',
              params: { id: String(offerId) },
            })
          }
          disabled={applications.isPending}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    paddingHorizontal: spacing.xl,
    paddingTop: spacing.md,
    borderTopWidth: 1,
  },
  status: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    minHeight: 52,
  },
});
