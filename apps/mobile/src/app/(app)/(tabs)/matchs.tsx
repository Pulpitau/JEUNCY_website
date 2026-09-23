import { useRouter } from 'expo-router';
import {
  ActivityIndicator,
  FlatList,
  RefreshControl,
  StyleSheet,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { VerificationGate } from '@/components/features/organization/verification-gate';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Text } from '@/components/ui/text';
import { statusTone } from '@/components/features/applications/status';
import { useMatches } from '@/hooks/use-matches';
import { useOrganizationRole } from '@/hooks/use-organization';
import { publisherOf } from '@/lib/api/job-offers';
import type { CandidateMatch, EmployerMatch } from '@/lib/api/matches';
import { APPLICATION_STATUS_LABELS } from '@/lib/labels';
import { formatRelativeFr } from '@/lib/relative-time';
import { useTheme } from '@/theme/theme-provider';
import { spacing } from '@/theme/typography';

// L'onglet « Matchs », des deux cotes.
//
// Un match n'est pas une fin, c'est un rendez-vous manque tant que personne
// ne bouge — et c'est TOUJOURS au candidat de bouger en premier : le match
// ouvre la conversation, il ne livre ni le CV ni les coordonnees (§4.3).
// Chaque carte dit donc, en toutes lettres, a qui est la prochaine action.
//
// Le cote candidat mene au formulaire de candidature, le cote employeur au
// detail (carte, puis dossier complet quand il arrive). Aucun des deux
// n'expose quoi que ce soit de plus que ce que le serveur a envoye.

export default function MatchsScreen() {
  const organizationRole = useOrganizationRole();

  if (organizationRole) {
    return (
      <VerificationGate>
        <EmployerMatches />
      </VerificationGate>
    );
  }

  return <CandidateMatches />;
}

// ---------------------------------------------------------------------------
// Candidat
// ---------------------------------------------------------------------------

function CandidateMatches() {
  const router = useRouter();
  const { data, isPending, error, refetch, isRefetching } = useMatches<CandidateMatch>();

  return (
    <MatchList
      title="Matchs"
      subtitle="Ces recruteurs veulent te lire. À toi d'envoyer ton dossier."
      isPending={isPending}
      error={error}
      isRefetching={isRefetching}
      onRefresh={() => void refetch()}
      data={data}
      empty={{
        title: 'Pas encore de match',
        description:
          'Un match arrive quand un recruteur et toi dites oui tous les deux. Continue à parcourir les offres dans Découvrir.',
        action: { label: 'Découvrir des offres', onPress: () => router.push('/') },
      }}
      keyOf={(match) => String(match.id)}
      renderItem={(match) => {
        const publisher = publisherOf(match.job_offer);
        const dossier = match.application;

        return (
          <Card>
            <View style={styles.head}>
              <Text variant="bodyStrong" style={styles.headText} numberOfLines={2}>
                {match.job_offer.title}
              </Text>
              <Badge
                label={dossier ? APPLICATION_STATUS_LABELS[dossier.status] : 'À envoyer'}
                tone={dossier ? statusTone(dossier.status) : 'warm'}
              />
            </View>

            <Text variant="small" tone="muted">
              {publisher?.name ?? 'Recruteur'} · match{' '}
              {formatRelativeFr(match.matched_at)}
            </Text>

            {dossier === null ? (
              <>
                <Text variant="small">
                  Envoie ton dossier pour que le recruteur puisse te répondre. Sans lui,
                  il ne voit que ta carte.
                </Text>
                <Button
                  label="Envoyer mon dossier"
                  onPress={() =>
                    router.push({
                      pathname: '/offres/[id]/postuler',
                      params: { id: String(match.job_offer.id) },
                    })
                  }
                />
              </>
            ) : (
              <Button
                label="Voir ma candidature"
                variant="ghost"
                onPress={() => router.push('/candidatures')}
              />
            )}
          </Card>
        );
      }}
    />
  );
}

// ---------------------------------------------------------------------------
// Entreprise et CFA
// ---------------------------------------------------------------------------

function EmployerMatches() {
  const router = useRouter();
  const { data, isPending, error, refetch, isRefetching } = useMatches<EmployerMatch>();

  return (
    <MatchList
      title="Matchs"
      subtitle="Ces candidats ont répondu à ton intérêt."
      isPending={isPending}
      error={error}
      isRefetching={isRefetching}
      onRefresh={() => void refetch()}
      data={data}
      empty={{
        title: 'Pas encore de match',
        description:
          'Un match arrive quand un candidat et toi dites oui tous les deux. Parcours des profils dans Découvrir.',
        action: { label: 'Découvrir des candidats', onPress: () => router.push('/') },
      }}
      keyOf={(match) => String(match.id)}
      renderItem={(match) => {
        const candidat = match.candidate;
        const nom = candidat
          ? [
              candidat.first_name,
              candidat.last_name_initial ? `${candidat.last_name_initial}.` : null,
            ]
              .filter(Boolean)
              .join(' ')
          : 'Candidat';
        const dossier = match.application;

        return (
          <Card
            onPress={() =>
              router.push({ pathname: '/matchs/[id]', params: { id: String(match.id) } })
            }
            accessibilityLabel={`Match avec ${nom}`}
          >
            <View style={styles.head}>
              <Text variant="bodyStrong" style={styles.headText} numberOfLines={1}>
                {nom}
              </Text>
              <Badge
                label={
                  dossier
                    ? APPLICATION_STATUS_LABELS[dossier.status]
                    : 'Attend son dossier'
                }
                tone={dossier ? statusTone(dossier.status) : 'warm'}
              />
            </View>

            <Text variant="small" tone="muted" numberOfLines={1}>
              {match.job_offer?.title ?? 'Offre supprimée'} · match{' '}
              {formatRelativeFr(match.matched_at)}
            </Text>

            {candidat?.headline ? (
              <Text variant="small" numberOfLines={1}>
                {candidat.headline}
              </Text>
            ) : null}

            <Text variant="small" tone="muted">
              {dossier
                ? 'Dossier reçu : nom complet, coordonnées et CV disponibles.'
                : "C'est à lui ou elle d'envoyer le dossier. On l'a prévenu·e."}
            </Text>
          </Card>
        );
      }}
    />
  );
}

// ---------------------------------------------------------------------------
// Coque commune
// ---------------------------------------------------------------------------

interface MatchListProps<T> {
  title: string;
  subtitle: string;
  isPending: boolean;
  error: unknown;
  isRefetching: boolean;
  onRefresh: () => void;
  data: T[] | undefined;
  empty: {
    title: string;
    description: string;
    action: { label: string; onPress: () => void };
  };
  keyOf: (item: T) => string;
  renderItem: (item: T) => React.ReactElement;
}

function MatchList<T>({
  title,
  subtitle,
  isPending,
  error,
  isRefetching,
  onRefresh,
  data,
  empty,
  keyOf,
  renderItem,
}: MatchListProps<T>) {
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();

  return (
    <View
      style={[
        styles.screen,
        { backgroundColor: colors.background, paddingTop: insets.top + spacing.lg },
      ]}
    >
      <View style={styles.header}>
        <Text variant="hero">{title}</Text>
        <Text variant="small" tone="muted">
          {subtitle}
        </Text>
      </View>

      {isPending ? (
        <View style={styles.centered}>
          <ActivityIndicator color={colors.accent} />
        </View>
      ) : error ? (
        <EmptyState
          title="Impossible de charger tes matchs"
          description={error instanceof Error ? error.message : undefined}
          action={{ label: 'Réessayer', onPress: onRefresh }}
        />
      ) : (
        <FlatList
          data={data ?? []}
          keyExtractor={keyOf}
          renderItem={({ item }) => renderItem(item)}
          contentContainerStyle={styles.list}
          ItemSeparatorComponent={Separator}
          refreshControl={
            <RefreshControl
              refreshing={isRefetching}
              onRefresh={onRefresh}
              tintColor={colors.accent}
            />
          }
          ListEmptyComponent={
            <EmptyState
              title={empty.title}
              description={empty.description}
              action={empty.action}
            />
          }
        />
      )}
    </View>
  );
}

function Separator() {
  return <View style={styles.separator} />;
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  header: { paddingHorizontal: spacing.xl, gap: 2, marginBottom: spacing.md },
  list: { paddingHorizontal: spacing.xl, paddingBottom: spacing.xxl },
  separator: { height: spacing.md },
  head: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.sm },
  headText: { flex: 1 },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center' },
});
