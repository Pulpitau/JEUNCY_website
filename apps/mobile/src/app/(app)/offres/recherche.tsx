import { ContractType, WorkMode } from '@jeuncy/shared';
import { useInfiniteQuery } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  RefreshControl,
  StyleSheet,
  View,
} from 'react-native';

import { JobOfferCard } from '@/components/features/job-offers/job-offer-card';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { ChipRow } from '@/components/ui/chip-row';
import { EmptyState } from '@/components/ui/empty-state';
import { Field } from '@/components/ui/field';
import { Text } from '@/components/ui/text';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import {
  EXTERNAL_SOURCE_LABEL,
  formatExternalLocation,
  searchExternalOffers,
  type ExternalJobOffer,
} from '@/lib/api/external-offers';
import { searchPublicOffers, type PublicJobOffer } from '@/lib/api/job-offers';
import { CONTRACT_TYPE_LABELS, WORK_MODE_LABELS } from '@/lib/labels';
import { useTheme } from '@/theme/theme-provider';
import { spacing } from '@/theme/typography';

const CONTRACT_OPTIONS = (Object.keys(CONTRACT_TYPE_LABELS) as ContractType[]).map(
  (value) => ({
    value,
    label: CONTRACT_TYPE_LABELS[value],
  }),
);

const WORK_MODE_OPTIONS = (Object.keys(WORK_MODE_LABELS) as WorkMode[]).map((value) => ({
  value,
  label: WORK_MODE_LABELS[value],
}));

type Row =
  | { kind: 'jeuncy'; key: string; offer: PublicJobOffer }
  | { kind: 'partners-title'; key: string }
  | { kind: 'lba'; key: string; offer: ExternalJobOffer };

const nextPage = (last: { current_page: number; last_page: number }) =>
  last.current_page < last.last_page ? last.current_page + 1 : undefined;

// Recherche par liste, avec filtres. C'etait l'onglet d'accueil du candidat
// jusqu'au prototype de pile (2026-09-22) : « Decouvrir » a pris sa place et
// la loupe de son en-tete mene ici. Ecran de pile avec en-tete natif, d'ou
// l'absence de marge d'encoche.
//
// Deux sources, comme /offres sur le site : les offres Jeuncy d'abord, puis
// les offres partenaires (La bonne alternance). Sans ces dernieres, « Toutes
// les offres » n'en montrait qu'une poignee en production, pour des milliers
// disponibles.
export default function OffresSearchScreen() {
  const router = useRouter();
  const { colors } = useTheme();

  const [keyword, setKeyword] = useState('');
  const [city, setCity] = useState('');
  const [contractType, setContractType] = useState<ContractType | null>(null);
  const [workMode, setWorkMode] = useState<WorkMode | null>(null);

  // Les champs texte sont temporises, les puces non : un tap est une intention
  // claire, une frappe au clavier ne l'est qu'une fois terminee.
  const q = useDebouncedValue(keyword.trim());
  const cityFilter = useDebouncedValue(city.trim());

  const filters = useMemo(
    () => ({
      q: q || undefined,
      city: cityFilter || undefined,
      contract_type: contractType ?? undefined,
      work_mode: workMode ?? undefined,
    }),
    [q, cityFilter, contractType, workMode],
  );

  // Les offres partenaires sont toutes des alternances : un autre type de
  // contrat demande les exclut.
  const withPartners = contractType === null || contractType === ContractType.ALTERNANCE;

  const query = useInfiniteQuery({
    queryKey: ['job-offers', 'search', filters],
    queryFn: ({ pageParam }) => searchPublicOffers({ ...filters, page: pageParam }),
    initialPageParam: 1,
    getNextPageParam: nextPage,
  });

  const partnerFilters = {
    q: filters.q,
    city: filters.city,
    work_mode: filters.work_mode,
  };
  const partners = useInfiniteQuery({
    queryKey: ['job-offers', 'external', 'search', partnerFilters],
    queryFn: ({ pageParam }) =>
      searchExternalOffers({ ...partnerFilters, page: pageParam }),
    initialPageParam: 1,
    getNextPageParam: nextPage,
    enabled: withPartners,
  });

  const offers: PublicJobOffer[] = query.data?.pages.flatMap((page) => page.data) ?? [];
  const partnerOffers: ExternalJobOffer[] = withPartners
    ? (partners.data?.pages.flatMap((page) => page.data) ?? [])
    : [];
  const total =
    (query.data?.pages[0]?.total ?? 0) +
    (withPartners ? (partners.data?.pages[0]?.total ?? 0) : 0);
  const hasFilters = Boolean(q || cityFilter || contractType || workMode);

  const rows: Row[] = [
    ...offers.map((offer) => ({ kind: 'jeuncy' as const, key: `j${offer.id}`, offer })),
    ...(partnerOffers.length > 0
      ? [{ kind: 'partners-title' as const, key: 'partners-title' }]
      : []),
    ...partnerOffers.map((offer) => ({
      kind: 'lba' as const,
      key: `p${offer.id}`,
      offer,
    })),
  ];

  const isPending = query.isPending || (withPartners && partners.isPending);
  const isSuccess = query.isSuccess && (!withPartners || partners.isSuccess);
  // Une seule source en echec ne vide pas l'ecran : l'autre reste lisible.
  const error =
    rows.length === 0 ? (query.error ?? (withPartners ? partners.error : null)) : null;

  const resetFilters = () => {
    setKeyword('');
    setCity('');
    setContractType(null);
    setWorkMode(null);
  };

  const loadMore = () => {
    // Les offres Jeuncy d'abord, en entier, puis les partenaires.
    if (query.hasNextPage) {
      if (!query.isFetchingNextPage) void query.fetchNextPage();

      return;
    }
    if (withPartners && partners.hasNextPage && !partners.isFetchingNextPage) {
      void partners.fetchNextPage();
    }
  };

  const refresh = () => {
    void query.refetch();
    if (withPartners) void partners.refetch();
  };

  const header = (
    <View style={styles.header}>
      <Text variant="title">Toutes les offres</Text>
      <Field
        label="Mot-clé"
        value={keyword}
        onChangeText={setKeyword}
        placeholder="Développeur, vente, boulangerie…"
        returnKeyType="search"
        autoCapitalize="none"
        clearButtonMode="while-editing"
      />
      <Field
        label="Ville"
        value={city}
        onChangeText={setCity}
        placeholder="Rennes, Nantes…"
        returnKeyType="search"
        autoCapitalize="words"
        clearButtonMode="while-editing"
      />
      <ChipRow
        options={CONTRACT_OPTIONS}
        value={contractType}
        onChange={setContractType}
        allLabel="Tous les contrats"
        accessibilityLabel="Type de contrat"
      />
      <ChipRow
        options={WORK_MODE_OPTIONS}
        value={workMode}
        onChange={setWorkMode}
        allLabel="Tous les modes"
        accessibilityLabel="Mode de travail"
      />
      {isSuccess ? (
        <Text variant="small" tone="muted" accessibilityLiveRegion="polite">
          {total === 0 ? 'Aucune offre' : total === 1 ? '1 offre' : `${total} offres`}
        </Text>
      ) : null}
    </View>
  );

  const footer =
    query.isFetchingNextPage || partners.isFetchingNextPage ? (
      <ActivityIndicator color={colors.accent} style={styles.footer} />
    ) : null;

  const empty = isPending ? (
    <ActivityIndicator color={colors.accent} style={styles.footer} />
  ) : error ? (
    <EmptyState
      title="Impossible de charger les offres"
      description={error.message}
      action={{ label: 'Réessayer', onPress: refresh }}
    />
  ) : (
    <EmptyState
      title="Aucune offre ne correspond"
      description={
        hasFilters
          ? 'Essaie avec moins de filtres, ou un autre mot-clé.'
          : 'Reviens bientôt, de nouvelles offres sont publiées régulièrement.'
      }
      action={
        hasFilters ? { label: 'Effacer les filtres', onPress: resetFilters } : undefined
      }
    />
  );

  return (
    <FlatList
      data={rows}
      keyExtractor={(row) => row.key}
      renderItem={({ item }) => {
        if (item.kind === 'jeuncy') {
          return (
            <JobOfferCard
              offer={item.offer}
              onPress={() =>
                router.push({
                  pathname: '/offres/[id]',
                  params: { id: String(item.offer.id) },
                })
              }
            />
          );
        }
        if (item.kind === 'partners-title') {
          return (
            <View style={styles.partnersTitle}>
              <Text variant="sectionTitle">Offres partenaires</Text>
              <Text variant="small" tone="muted">
                Publiées sur {EXTERNAL_SOURCE_LABEL}. Tu postules sur le site de
                l&apos;employeur.
              </Text>
            </View>
          );
        }

        return (
          <PartnerRow
            offer={item.offer}
            onPress={() =>
              router.push({
                pathname: '/offres/partenaire/[id]',
                params: { id: String(item.offer.id) },
              })
            }
          />
        );
      }}
      ListHeaderComponent={header}
      ListFooterComponent={footer}
      ListEmptyComponent={empty}
      contentContainerStyle={[
        styles.list,
        { paddingTop: spacing.lg, backgroundColor: colors.background },
      ]}
      style={{ backgroundColor: colors.background }}
      onEndReached={loadMore}
      onEndReachedThreshold={0.5}
      refreshControl={
        <RefreshControl
          refreshing={
            (query.isRefetching && !query.isFetchingNextPage) ||
            (partners.isRefetching && !partners.isFetchingNextPage)
          }
          onRefresh={refresh}
          tintColor={colors.accent}
        />
      }
      keyboardShouldPersistTaps="handled"
      keyboardDismissMode="on-drag"
    />
  );
}

function PartnerRow({
  offer,
  onPress,
}: {
  offer: ExternalJobOffer;
  onPress: () => void;
}) {
  const location = formatExternalLocation(offer);

  return (
    <Card onPress={onPress} accessibilityLabel={`${offer.title}, offre partenaire`}>
      <View style={styles.badges}>
        <Badge label="Alternance" tone="warm" />
        {offer.work_mode ? <Badge label={WORK_MODE_LABELS[offer.work_mode]} /> : null}
      </View>
      <Text variant="sectionTitle">{offer.title}</Text>
      <Text variant="small" tone="muted">
        {[offer.company_name ?? 'Employeur non communiqué', location]
          .filter(Boolean)
          .join(' · ')}
      </Text>
    </Card>
  );
}

const styles = StyleSheet.create({
  list: {
    paddingHorizontal: spacing.xl,
    paddingBottom: spacing.xxl,
    gap: spacing.md,
  },
  header: {
    gap: spacing.md,
    marginBottom: spacing.sm,
  },
  footer: {
    paddingVertical: spacing.xl,
  },
  partnersTitle: {
    gap: spacing.xs,
    marginTop: spacing.lg,
  },
  badges: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
});
