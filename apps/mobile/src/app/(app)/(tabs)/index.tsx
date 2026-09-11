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
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { JobOfferCard } from '@/components/features/job-offers/job-offer-card';
import { ChipRow } from '@/components/ui/chip-row';
import { EmptyState } from '@/components/ui/empty-state';
import { Field } from '@/components/ui/field';
import { Text } from '@/components/ui/text';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
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

export default function OffresScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();

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

  const query = useInfiniteQuery({
    queryKey: ['job-offers', 'search', filters],
    queryFn: ({ pageParam }) => searchPublicOffers({ ...filters, page: pageParam }),
    initialPageParam: 1,
    getNextPageParam: (last) =>
      last.current_page < last.last_page ? last.current_page + 1 : undefined,
  });

  const offers: PublicJobOffer[] = query.data?.pages.flatMap((page) => page.data) ?? [];
  const total = query.data?.pages[0]?.total ?? 0;
  const hasFilters = Boolean(q || cityFilter || contractType || workMode);

  const resetFilters = () => {
    setKeyword('');
    setCity('');
    setContractType(null);
    setWorkMode(null);
  };

  const header = (
    <View style={styles.header}>
      <Text variant="hero">Offres</Text>
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
      {query.isSuccess ? (
        <Text variant="small" tone="muted" accessibilityLiveRegion="polite">
          {total === 0 ? 'Aucune offre' : total === 1 ? '1 offre' : `${total} offres`}
        </Text>
      ) : null}
    </View>
  );

  const footer = query.isFetchingNextPage ? (
    <ActivityIndicator color={colors.accent} style={styles.footer} />
  ) : null;

  const empty = query.isPending ? (
    <ActivityIndicator color={colors.accent} style={styles.footer} />
  ) : query.isError ? (
    <EmptyState
      title="Impossible de charger les offres"
      description={query.error.message}
      action={{ label: 'Réessayer', onPress: () => void query.refetch() }}
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
      data={offers}
      keyExtractor={(offer) => String(offer.id)}
      renderItem={({ item }) => (
        <JobOfferCard
          offer={item}
          onPress={() =>
            router.push({ pathname: '/offres/[id]', params: { id: String(item.id) } })
          }
        />
      )}
      ListHeaderComponent={header}
      ListFooterComponent={footer}
      ListEmptyComponent={empty}
      contentContainerStyle={[
        styles.list,
        { paddingTop: insets.top + spacing.lg, backgroundColor: colors.background },
      ]}
      style={{ backgroundColor: colors.background }}
      // Charge la page suivante quand il reste moins d'un demi-ecran a
      // parcourir : le candidat ne voit jamais le bas de la liste arriver.
      onEndReached={() => {
        if (query.hasNextPage && !query.isFetchingNextPage) void query.fetchNextPage();
      }}
      onEndReachedThreshold={0.5}
      refreshControl={
        <RefreshControl
          refreshing={query.isRefetching && !query.isFetchingNextPage}
          onRefresh={() => void query.refetch()}
          tintColor={colors.accent}
        />
      }
      keyboardShouldPersistTaps="handled"
      keyboardDismissMode="on-drag"
    />
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
});
