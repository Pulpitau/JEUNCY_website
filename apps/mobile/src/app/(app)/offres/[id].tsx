import { useQuery } from '@tanstack/react-query';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import { ActivityIndicator, ScrollView, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { PublisherAvatar } from '@/components/features/job-offers/publisher-avatar';
import { Badge } from '@/components/ui/badge';
import { EmptyState } from '@/components/ui/empty-state';
import { Text } from '@/components/ui/text';
import { ApiError } from '@/lib/api/client';
import { getPublicOffer, isCfaOffer, publisherOf } from '@/lib/api/job-offers';
import { formatCompensation } from '@/lib/format-compensation';
import { CONTRACT_TYPE_LABELS, WORK_MODE_LABELS } from '@/lib/labels';
import { useTheme } from '@/theme/theme-provider';
import { spacing } from '@/theme/typography';

// Ligne « Libelle : valeur », l'equivalent du <dl> du web.
function Fact({ label, value }: { label: string; value: string | null | undefined }) {
  if (!value) return null;

  return (
    <Text variant="body">
      <Text variant="body" tone="muted">
        {label} :{' '}
      </Text>
      <Text variant="bodyStrong">{value}</Text>
    </Text>
  );
}

// Rendu public d'une offre, aligne sur PublicJobOfferView (web) : memes
// rubriques, meme ordre, memes intitules selon qu'il s'agit d'une entreprise
// ou d'un CFA. Le bouton « Postuler » arrive avec le lot C (candidature).
export default function OffreDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();
  const offerId = Number(id);

  const query = useQuery({
    queryKey: ['job-offers', offerId],
    queryFn: () => getPublicOffer(offerId),
    enabled: Number.isInteger(offerId) && offerId > 0,
  });

  if (query.isPending) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator color={colors.accent} />
      </View>
    );
  }

  if (query.isError) {
    // Une offre retiree, expiree ou jamais publiee repond 404 : le candidat
    // arrive parfois ici par une notification ancienne, il faut le lui dire
    // plutot que d'afficher une erreur generique.
    const introuvable = query.error instanceof ApiError && query.error.status === 404;

    return (
      <EmptyState
        title={
          introuvable
            ? "Cette offre n'est plus disponible"
            : "Impossible d'afficher l'offre"
        }
        description={
          introuvable
            ? 'Elle a peut-être été pourvue ou retirée par son auteur.'
            : query.error.message
        }
        action={{ label: 'Voir les autres offres', onPress: () => router.replace('/') }}
      />
    );
  }

  const offer = query.data;
  const publisher = publisherOf(offer);
  const cfa = isCfaOffer(offer);
  const remuneration = formatCompensation(
    offer.compensation_amount,
    offer.compensation_period,
    offer.compensation,
  );

  return (
    <>
      <Stack.Screen options={{ title: publisher?.name ?? 'Offre' }} />
      <ScrollView
        contentContainerStyle={[
          styles.content,
          { paddingBottom: insets.bottom + spacing.xxl },
        ]}
        style={{ backgroundColor: colors.background }}
      >
        <Badge label={CONTRACT_TYPE_LABELS[offer.contract_type]} tone="accent" />
        <Text variant="title">{offer.title}</Text>

        <View style={styles.publisher}>
          <PublisherAvatar publisher={publisher} size={36} />
          <Text variant="body" tone="muted" style={styles.publisherName}>
            {publisher?.name}
            {offer.city ? ` · ${offer.city}` : ''}
          </Text>
        </View>

        <View style={styles.facts}>
          <Fact
            label="Type d'offre"
            value={offer.work_mode && WORK_MODE_LABELS[offer.work_mode]}
          />
          <Fact label="Rémunération" value={remuneration} />
          {!cfa ? (
            <Fact label="Expérience requise" value={offer.experience_level} />
          ) : null}
          {cfa ? <Fact label="Niveau visé" value={offer.diploma_level} /> : null}
          {cfa ? (
            <Fact label="Rythme de l'alternance" value={offer.training_rhythm} />
          ) : null}
        </View>

        <Text variant="body" style={styles.description}>
          {offer.description}
        </Text>

        {offer.skills.length > 0 ? (
          <View style={styles.section}>
            <Text variant="sectionTitle">
              {cfa ? 'Compétences et expériences acquises' : 'Compétences recherchées'}
            </Text>
            <View style={styles.skills}>
              {offer.skills.map((skill) => (
                <Badge key={skill.id} label={skill.name} />
              ))}
            </View>
          </View>
        ) : null}

        {!cfa && offer.benefits ? (
          <View style={styles.section}>
            <Text variant="sectionTitle">Avantages</Text>
            <Text variant="body" tone="muted">
              {offer.benefits}
            </Text>
          </View>
        ) : null}
      </ScrollView>
    </>
  );
}

const styles = StyleSheet.create({
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  content: {
    padding: spacing.xl,
    gap: spacing.md,
  },
  publisher: { flexDirection: 'row', alignItems: 'center', gap: spacing.md },
  publisherName: { flex: 1 },
  facts: { gap: spacing.xs, marginTop: spacing.sm },
  description: { marginTop: spacing.sm },
  section: { gap: spacing.sm, marginTop: spacing.md },
  skills: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
});
