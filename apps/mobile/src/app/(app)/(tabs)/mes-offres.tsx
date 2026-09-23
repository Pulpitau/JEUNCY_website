import { Ionicons } from '@expo/vector-icons';
import { useMutation } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Text } from '@/components/ui/text';
import {
  useInvalidateMyOffers,
  useMyOffers,
  whyNotDiscoverable,
} from '@/hooks/use-my-offers';
import { archiveOffer, publishOffer, type JobOffer } from '@/lib/api/job-offers';
import { CONTRACT_TYPE_LABELS } from '@/lib/labels';
import { useTheme } from '@/theme/theme-provider';
import { spacing } from '@/theme/typography';

// « Mes offres » : la gestion, pas la pile.
//
// Chaque offre porte deux informations que l'employeur ne trouve nulle part
// ailleurs : son statut de publication, et ce qui lui manque pour proposer
// des candidats. La seconde est la plus importante — une offre publiee sans
// code postal est invisible de Decouvrir, et rien dans l'ecran de Decouvrir
// ne peut le dire offre par offre.
//
// L'ecriture longue d'une offre (description, remuneration, missions) reste
// sur le site : un formulaire de cette taille ne se remplit pas au pouce.

export default function MesOffresScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();
  const { data, isPending, error, refetch } = useMyOffers();

  const publiees = (data ?? []).filter((offer) => offer.status === 'PUBLISHED');
  const autres = (data ?? []).filter((offer) => offer.status !== 'PUBLISHED');

  return (
    <View
      style={[
        styles.screen,
        { backgroundColor: colors.background, paddingTop: insets.top + spacing.lg },
      ]}
    >
      <View style={styles.header}>
        <Text variant="hero">Mes offres</Text>
        <Text variant="small" tone="muted">
          Publier est gratuit, sans limite.
        </Text>
      </View>

      {isPending ? (
        <View style={styles.centered}>
          <ActivityIndicator color={colors.accent} />
        </View>
      ) : error ? (
        <EmptyState
          title="Impossible de charger tes offres"
          description={error instanceof Error ? error.message : undefined}
          action={{ label: 'Réessayer', onPress: () => void refetch() }}
        />
      ) : (
        <ScrollView contentContainerStyle={styles.list}>
          <Button
            label="Créer une offre express"
            onPress={() => router.push('/organisation/offre-express')}
          />

          {publiees.length === 0 && autres.length === 0 ? (
            <EmptyState
              title="Aucune offre pour l'instant"
              description="Une offre express suffit pour commencer : un intitulé, un contrat, une commune. Tu la compléteras ensuite."
            />
          ) : null}

          {publiees.map((offer) => (
            <OfferCard key={offer.id} offer={offer} />
          ))}

          {autres.length > 0 ? (
            <Text variant="label" tone="muted" style={styles.group}>
              NON PUBLIÉES
            </Text>
          ) : null}
          {autres.map((offer) => (
            <OfferCard key={offer.id} offer={offer} />
          ))}
        </ScrollView>
      )}
    </View>
  );
}

function OfferCard({ offer }: { offer: JobOffer }) {
  const router = useRouter();
  const { colors } = useTheme();
  const invalidateOffers = useInvalidateMyOffers();

  const publiee = offer.status === 'PUBLISHED';
  const manque = whyNotDiscoverable(offer);

  const publier = useMutation({
    mutationFn: () => publishOffer(offer.id),
    onSuccess: invalidateOffers,
    onError: (cause: Error) => Alert.alert('Publication impossible', cause.message),
  });

  const archiver = useMutation({
    mutationFn: () => archiveOffer(offer.id),
    onSuccess: invalidateOffers,
    onError: (cause: Error) => Alert.alert('Archivage impossible', cause.message),
  });

  const confirmerArchivage = () =>
    Alert.alert(
      'Archiver cette offre ?',
      'Elle disparaîtra des recherches et des piles. Les matchs en cours seront fermés et les candidats prévenus.',
      [
        { text: 'Annuler', style: 'cancel' },
        { text: 'Archiver', style: 'destructive', onPress: () => archiver.mutate() },
      ],
    );

  return (
    <Card>
      <View style={styles.cardHead}>
        <Text variant="bodyStrong" style={styles.cardTitle} numberOfLines={2}>
          {offer.title}
        </Text>
        <Badge
          label={
            publiee ? 'Publiée' : offer.status === 'DRAFT' ? 'Brouillon' : 'Archivée'
          }
          tone={publiee ? 'success' : 'neutral'}
        />
      </View>

      <Text variant="small" tone="muted">
        {CONTRACT_TYPE_LABELS[offer.contract_type]}
        {offer.city ? ` · ${offer.city}` : ''}
        {offer.postal_code ? ` (${offer.postal_code})` : ''}
      </Text>

      {manque ? (
        <View style={styles.warn}>
          <Ionicons name="alert-circle-outline" size={16} color={colors.accentWarm} />
          <Text variant="small" style={styles.warnText}>
            {manque === 'Code postal manquant'
              ? "Sans code postal, cette offre n'apparaît dans aucune pile. Complète-la sur jeuncy.com."
              : 'Publie cette offre pour découvrir des candidats.'}
          </Text>
        </View>
      ) : (
        <View style={styles.actionsRow}>
          <Ionicons name="checkmark-circle-outline" size={16} color={colors.success} />
          <Text variant="small" tone="muted" style={styles.warnText}>
            Prête pour Découvrir.
          </Text>
        </View>
      )}

      {publiee ? (
        <Pressable
          onPress={() =>
            router.push({
              pathname: '/offres/[id]/candidatures',
              params: { id: String(offer.id) },
            })
          }
          accessibilityRole="button"
          accessibilityLabel={`Candidatures reçues sur ${offer.title}`}
          style={({ pressed }) => [styles.link, { opacity: pressed ? 0.7 : 1 }]}
        >
          <Text variant="bodyStrong" tone="accent">
            Candidatures reçues
          </Text>
          <Ionicons name="chevron-forward" size={18} color={colors.accent} />
        </Pressable>
      ) : null}

      {publiee ? (
        <Button
          label="Archiver"
          variant="ghost"
          onPress={confirmerArchivage}
          loading={archiver.isPending}
        />
      ) : offer.status === 'DRAFT' ? (
        <Button
          label="Publier — gratuit"
          onPress={() => publier.mutate()}
          loading={publier.isPending}
        />
      ) : null}
    </Card>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  header: { paddingHorizontal: spacing.xl, gap: 2, marginBottom: spacing.md },
  list: {
    paddingHorizontal: spacing.xl,
    paddingBottom: spacing.xxl,
    gap: spacing.md,
  },
  group: { marginTop: spacing.lg },
  cardHead: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.sm },
  cardTitle: { flex: 1 },
  warn: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.sm },
  warnText: { flex: 1 },
  actionsRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  link: { flexDirection: 'row', alignItems: 'center', gap: spacing.xs },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center' },
});
