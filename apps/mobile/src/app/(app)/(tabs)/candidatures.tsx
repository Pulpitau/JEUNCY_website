import { Ionicons } from '@expo/vector-icons';
import { useMutation } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  RefreshControl,
  StyleSheet,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { KeptOffersSection } from '@/components/features/applications/kept-offers-section';
import { statusTone } from '@/components/features/applications/status';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Screen } from '@/components/ui/screen';
import { Text } from '@/components/ui/text';
import { useInterestedOffers } from '@/hooks/use-interested-offers';
import { useKeptOffers } from '@/hooks/use-kept-offers';
import {
  useInvalidateApplications,
  useMyApplications,
} from '@/hooks/use-my-applications';
import { withdrawApplication, type ApplicationWithOffer } from '@/lib/api/applications';
import { ApiError } from '@/lib/api/client';
import { formatDateFr } from '@/lib/dates';
import { APPLICATION_STATUS_LABELS, CONTRACT_TYPE_LABELS } from '@/lib/labels';
import { useOrganizationRole } from '@/hooks/use-organization';
import { useTheme } from '@/theme/theme-provider';
import { spacing } from '@/theme/typography';

// Un onglet, deux contenus : le suivi de ses candidatures pour un candidat,
// les candidatures recues pour une entreprise ou un CFA (lot G).
export default function CandidaturesScreen() {
  const organizationRole = useOrganizationRole();

  return organizationRole ? <CandidaturesRecues /> : <MesCandidatures />;
}

// Provisoire : les candidatures recues arrivent avec le lot G.
function CandidaturesRecues() {
  return (
    <Screen>
      <Text variant="hero">Candidatures reçues</Text>
      <EmptyState
        title="Bientôt ici"
        description="Les candidatures à tes offres, avec le CV de chaque candidat et le suivi de ta réponse : prochaine étape."
      />
    </Screen>
  );
}

// Suivi des candidatures, equivalent de /mes-candidatures (web). Un tap
// ouvre l'offre ; un appui long propose le retrait, definitif.
function MesCandidatures() {
  const router = useRouter();
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();
  const applications = useMyApplications();
  const invalidate = useInvalidateApplications();
  const kept = useKeptOffers();
  const interested = useInterestedOffers();
  const nbInteresses = interested.data?.length ?? 0;

  const withdraw = useMutation({
    mutationFn: withdrawApplication,
    onSuccess: () => void invalidate(),
    onError: (error) =>
      Alert.alert(
        'Retrait impossible',
        error instanceof ApiError ? error.message : 'Réessaie dans un instant.',
      ),
  });

  const confirmerRetrait = (application: ApplicationWithOffer) => {
    Alert.alert(
      'Retirer cette candidature ?',
      `L'entreprise ne verra plus ta candidature à « ${application.job_offer.title} ». Tu pourras postuler à nouveau.`,
      [
        { text: 'Annuler', style: 'cancel' },
        {
          text: 'Retirer',
          style: 'destructive',
          onPress: () => withdraw.mutate(application.id),
        },
      ],
    );
  };

  const header = (
    <View style={styles.header}>
      <Text variant="hero">Candidatures</Text>
      {/* Point d'entrée de la notification « X s'intéresse à toi ». */}
      <Card
        onPress={() => router.push('/interesses')}
        accessibilityLabel={
          nbInteresses > 0
            ? `Ils s'intéressent à toi : ${nbInteresses} offre${nbInteresses > 1 ? 's' : ''}`
            : "Ils s'intéressent à toi"
        }
      >
        <View style={styles.interestRow}>
          <Ionicons name="heart" size={20} color={colors.accent} />
          <View style={styles.interestText}>
            <Text variant="bodyStrong">Ils s&apos;intéressent à toi</Text>
            <Text variant="small" tone="muted">
              {nbInteresses > 0
                ? `${nbInteresses} recruteur${nbInteresses > 1 ? 's attendent' : ' attend'} ta réponse.`
                : 'Les recruteurs qui ont aimé ton profil apparaissent ici.'}
            </Text>
          </View>
          {nbInteresses > 0 ? <Badge label={String(nbInteresses)} tone="accent" /> : null}
          <Ionicons name="chevron-forward" size={16} color={colors.textMuted} />
        </View>
      </Card>
    </View>
  );

  const empty = applications.isPending ? (
    <ActivityIndicator color={colors.accent} style={styles.spinner} />
  ) : applications.isError ? (
    <EmptyState
      title="Impossible de charger tes candidatures"
      description={applications.error.message}
      action={{ label: 'Réessayer', onPress: () => void applications.refetch() }}
    />
  ) : (
    <EmptyState
      title="Aucune candidature pour l'instant"
      description="Quand tu postules à une offre, tu suis ici la réponse de l'entreprise."
      action={{ label: 'Voir les offres', onPress: () => router.push('/') }}
    />
  );

  return (
    <FlatList
      data={applications.data ?? []}
      keyExtractor={(application) => String(application.id)}
      renderItem={({ item }) => (
        <ApplicationCard
          application={item}
          onPress={() =>
            router.push({
              pathname: '/offres/[id]',
              params: { id: String(item.job_offer_id) },
            })
          }
          onWithdraw={() => confirmerRetrait(item)}
        />
      )}
      ListHeaderComponent={header}
      ListEmptyComponent={empty}
      ListFooterComponent={<KeptOffersSection />}
      contentContainerStyle={[styles.list, { paddingTop: insets.top + spacing.lg }]}
      style={{ backgroundColor: colors.background }}
      refreshControl={
        <RefreshControl
          refreshing={applications.isRefetching || kept.isRefetching}
          onRefresh={() => {
            void applications.refetch();
            void kept.refetch();
            void interested.refetch();
          }}
          tintColor={colors.accent}
        />
      }
    />
  );
}

function ApplicationCard({
  application,
  onPress,
  onWithdraw,
}: {
  application: ApplicationWithOffer;
  onPress: () => void;
  onWithdraw: () => void;
}) {
  const offer = application.job_offer;

  return (
    <Card
      onPress={onPress}
      onLongPress={onWithdraw}
      accessibilityLabel={`${offer.title}, ${APPLICATION_STATUS_LABELS[application.status]}`}
    >
      <View style={styles.cardHeader}>
        <Badge label={CONTRACT_TYPE_LABELS[offer.contract_type]} />
        <Badge
          label={APPLICATION_STATUS_LABELS[application.status]}
          tone={statusTone(application.status)}
        />
      </View>
      <Text variant="sectionTitle">{offer.title}</Text>
      <Text variant="small" tone="muted">
        {offer.city ? `${offer.city} · ` : ''}
        Envoyée le {formatDateFr(application.created_at)}
      </Text>
      {/* Visible plutôt que caché derrière un appui long : le retrait doit se
          trouver sans mode d'emploi, et rester accessible à VoiceOver. */}
      <Button label="Retirer ma candidature" variant="ghost" onPress={onWithdraw} />
    </Card>
  );
}

const styles = StyleSheet.create({
  list: { paddingHorizontal: spacing.xl, paddingBottom: spacing.xxl, gap: spacing.md },
  header: { gap: spacing.md, marginBottom: spacing.sm },
  interestRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.md },
  interestText: { flex: 1, gap: 2 },
  spinner: { paddingVertical: spacing.xxl },
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', gap: spacing.sm },
});
