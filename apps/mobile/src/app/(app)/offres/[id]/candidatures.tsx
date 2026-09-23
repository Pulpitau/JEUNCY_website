import { useQuery } from '@tanstack/react-query';
import { Redirect, Stack, useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { ReceivedApplicationCard } from '@/components/features/applications/received-application';
import { VerificationGate } from '@/components/features/organization/verification-gate';
import { EmptyState } from '@/components/ui/empty-state';
import { Screen } from '@/components/ui/screen';
import { Text } from '@/components/ui/text';
import { useMyOffers } from '@/hooks/use-my-offers';
import { useOrganizationRole } from '@/hooks/use-organization';
import { listOfferApplications } from '@/lib/api/applications';
import { useTheme } from '@/theme/theme-provider';
import { spacing } from '@/theme/typography';

// Les dossiers recus sur une offre.
//
// Offre par offre, et pas « toutes mes candidatures » : une entreprise ne
// traite pas un tas, elle traite un poste. C'est aussi ce qui donne son sens
// au changement de statut — « Vue », « Entretien », « Acceptée » se decident
// en comparant des candidats sur le meme poste, pas dans l'absolu.

function applicationsKey(offerId: number) {
  return ['applications', 'offer', offerId] as const;
}

export default function CandidaturesRecuesScreen() {
  const organizationRole = useOrganizationRole();
  const { id } = useLocalSearchParams<{ id: string }>();

  // Cet ecran vit sous /offres/[id]/, a cote du detail public : un candidat
  // qui y arriverait par un lien doit retomber sur l'offre, pas sur une
  // erreur.
  if (!organizationRole) {
    return <Redirect href={{ pathname: '/offres/[id]', params: { id } }} />;
  }

  return (
    <VerificationGate>
      <Recues offerId={Number(id)} />
    </VerificationGate>
  );
}

function Recues({ offerId }: { offerId: number }) {
  const { colors } = useTheme();
  const { data: offers } = useMyOffers();
  const offer = offers?.find((candidate) => candidate.id === offerId) ?? null;

  const { data, isPending, error, refetch } = useQuery({
    queryKey: applicationsKey(offerId),
    queryFn: () => listOfferApplications(offerId),
  });

  return (
    <Screen hasHeader>
      {/* Le titre porte l'intitule du poste : dans une pile d'ecrans, « Candidatures
          reçues » seul ne dit pas de quelle offre on parle. */}
      <Stack.Screen options={{ title: offer?.title ?? 'Candidatures reçues' }} />

      <Text variant="hero">Candidatures reçues</Text>
      {offer ? (
        <Text variant="small" tone="muted" style={styles.subtitle}>
          {offer.title}
        </Text>
      ) : null}

      {isPending ? (
        <View style={styles.centered}>
          <ActivityIndicator color={colors.accent} />
        </View>
      ) : error ? (
        <EmptyState
          title="Impossible de charger les candidatures"
          description={error instanceof Error ? error.message : undefined}
          action={{ label: 'Réessayer', onPress: () => void refetch() }}
        />
      ) : (data ?? []).length === 0 ? (
        <EmptyState
          title="Aucune candidature pour l'instant"
          description="Les candidats qui matchent avec cette offre sont invités à envoyer leur dossier. Tu seras prévenu·e dès qu'il en arrive un."
        />
      ) : (
        <View style={styles.list}>
          {(data ?? []).map((application) => (
            <ReceivedApplicationCard
              key={application.id}
              application={application}
              invalidateKey={applicationsKey(offerId)}
            />
          ))}
        </View>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  subtitle: { marginBottom: spacing.lg },
  list: { gap: spacing.md, marginTop: spacing.md },
  centered: { paddingVertical: spacing.xxl, alignItems: 'center' },
});
