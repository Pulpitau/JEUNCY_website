import { Ionicons } from '@expo/vector-icons';
import { useQuery } from '@tanstack/react-query';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { ActivityIndicator, ScrollView, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Text } from '@/components/ui/text';
import { ApiError } from '@/lib/api/client';
import {
  EXTERNAL_SOURCE_LABEL,
  formatExternalLocation,
  getExternalOffer,
  type ExternalJobOffer,
} from '@/lib/api/external-offers';
import { formatDateFr } from '@/lib/dates';
import { WORK_MODE_LABELS } from '@/lib/labels';
import { swipeKeyFor, useSwipeStore } from '@/store/swipe-store';
import { useTheme } from '@/theme/theme-provider';
import { spacing } from '@/theme/typography';

// Ligne « Libelle : valeur », comme sur la fiche d'une offre Jeuncy.
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

// Fiche d'une offre partenaire (La bonne alternance), alignee sur
// ExternalJobOfferDetail (web). Pas de candidature Jeuncy : le candidat
// postule chez l'employeur, sur son site — c'est dit avant qu'il appuie.
// « Je garde » range l'offre dans l'onglet Candidatures, section « Gardees ».
export default function OffrePartenaireScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();
  const offerId = Number(id);

  const query = useQuery({
    queryKey: ['job-offers', 'external', offerId],
    queryFn: () => getExternalOffer(offerId),
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
    // L'import de nuit retire les offres absentes de l'export : une offre
    // gardee hier peut avoir disparu. Le dire, plutot qu'une erreur brute.
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
            ? "Elle a été pourvue ou retirée par l'employeur."
            : query.error.message
        }
        action={{ label: 'Voir les autres offres', onPress: () => router.replace('/') }}
      />
    );
  }

  return <OffrePartenaire offer={query.data} bottomInset={insets.bottom} />;
}

function OffrePartenaire({
  offer,
  bottomInset,
}: {
  offer: ExternalJobOffer;
  bottomInset: number;
}) {
  const { colors } = useTheme();
  const key = swipeKeyFor('lba', offer.id);
  const kept = useSwipeStore((state) => state.gestures[key]?.decision === 'KEEP');
  const record = useSwipeStore((state) => state.record);
  const forget = useSwipeStore((state) => state.forget);

  const toggleKeep = () => {
    if (kept) {
      forget(key);

      return;
    }
    record({
      key,
      decision: 'KEEP',
      kept: {
        id: offer.id,
        title: offer.title,
        employer: offer.company_name,
        city: offer.city,
        applyUrl: offer.apply_url,
      },
    });
  };

  const location = formatExternalLocation(offer);
  const start = offer.contract_start ? formatDateFr(offer.contract_start) : null;
  const duration =
    offer.contract_duration_months !== null
      ? `${offer.contract_duration_months} mois`
      : null;

  return (
    <>
      <Stack.Screen options={{ title: offer.company_name ?? 'Offre partenaire' }} />
      <View style={[styles.page, { backgroundColor: colors.background }]}>
        <ScrollView
          contentContainerStyle={[styles.content, { paddingBottom: spacing.xxl }]}
          style={{ backgroundColor: colors.background }}
        >
          <View style={styles.badges}>
            <Badge label="Alternance" tone="warm" />
            {offer.work_mode ? <Badge label={WORK_MODE_LABELS[offer.work_mode]} /> : null}
            <Badge label={`via ${EXTERNAL_SOURCE_LABEL}`} />
          </View>

          <Text variant="title">{offer.title}</Text>

          <View style={styles.employer}>
            <Ionicons name="business-outline" size={18} color={colors.textMuted} />
            <Text variant="body" tone="muted" style={styles.employerName}>
              {offer.company_name ?? 'Employeur non communiqué'}
              {location ? ` · ${location}` : ''}
            </Text>
          </View>

          <View style={styles.facts}>
            <Fact label="Début du contrat" value={start} />
            <Fact label="Durée" value={duration} />
            <Fact label="Diplôme visé" value={offer.target_diploma_label} />
            <Fact
              label="Postes ouverts"
              value={offer.opening_count !== null ? String(offer.opening_count) : null}
            />
          </View>

          <View style={styles.section}>
            <Text variant="sectionTitle">Le poste</Text>
            <Text variant="body">{offer.description}</Text>
          </View>

          {offer.company_naf_label || offer.company_size ? (
            <View style={styles.section}>
              <Text variant="sectionTitle">L&apos;employeur</Text>
              <Fact label="Secteur" value={offer.company_naf_label} />
              <Fact
                label="Effectif"
                value={offer.company_size ? `${offer.company_size} salariés` : null}
              />
            </View>
          ) : null}

          <View style={styles.section}>
            <Text variant="small" tone="muted">
              Offre publiée sur {EXTERNAL_SOURCE_LABEL}, le service public de
              l&apos;alternance. Tu postules directement auprès de l&apos;employeur, sur
              son site : pense à joindre le CV que tu as généré sur Jeuncy.
            </Text>
          </View>
        </ScrollView>

        <View
          style={[
            styles.bar,
            {
              backgroundColor: colors.surface,
              borderTopColor: colors.border,
              paddingBottom: bottomInset + spacing.md,
            },
          ]}
        >
          <Button
            label="Postuler sur le site de l'employeur"
            onPress={() => void WebBrowser.openBrowserAsync(offer.apply_url)}
          />
          <Button
            label={kept ? 'Retirer de mes offres gardées' : 'Je garde'}
            variant="secondary"
            onPress={toggleKeep}
          />
        </View>
      </View>
    </>
  );
}

const styles = StyleSheet.create({
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  page: { flex: 1 },
  content: {
    padding: spacing.xl,
    gap: spacing.md,
  },
  badges: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  employer: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  employerName: { flex: 1 },
  facts: { gap: spacing.xs, marginTop: spacing.sm },
  section: { gap: spacing.sm, marginTop: spacing.md },
  bar: {
    gap: spacing.sm,
    paddingHorizontal: spacing.xl,
    paddingTop: spacing.md,
    borderTopWidth: 1,
  },
});
