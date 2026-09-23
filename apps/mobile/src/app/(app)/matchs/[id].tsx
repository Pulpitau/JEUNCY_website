import { Ionicons } from '@expo/vector-icons';
import { Redirect, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';

import { ReceivedApplicationCard } from '@/components/features/applications/received-application';
import { ReportSheet } from '@/components/features/moderation/report-sheet';
import { CandidateCardFace } from '@/components/features/discover/candidate-card';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Screen } from '@/components/ui/screen';
import { Text } from '@/components/ui/text';
import { MATCHES_KEY, useMatch } from '@/hooks/use-matches';
import { useOrganizationRole } from '@/hooks/use-organization';
import { isEmployerMatch, type EmployerMatch } from '@/lib/api/matches';
import { formatRelativeFr } from '@/lib/relative-time';
import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

// Detail d'un match, cote entreprise et CFA.
//
// Deux etats, et la difference entre les deux est tout le produit :
//
//   - tant que le dossier n'est pas parti, l'employeur voit la CARTE, celle
//     du deck, rien de plus. Le match ouvre la conversation, il ne livre pas
//     les coordonnees ;
//   - des que le dossier arrive, il voit le dossier complet.
//
// Le cote candidat n'a pas d'ecran de detail : sa prochaine action est le
// formulaire de candidature, ou il va directement depuis la liste.

export default function MatchDetailScreen() {
  const organizationRole = useOrganizationRole();
  const { id } = useLocalSearchParams<{ id: string }>();
  const matchId = Number(id);

  // Un candidat n'a rien a lire ici ; sa liste mene au formulaire.
  if (!organizationRole) return <Redirect href="/matchs" />;

  return <EmployerMatchDetail matchId={matchId} />;
}

function EmployerMatchDetail({ matchId }: { matchId: number }) {
  const { colors } = useTheme();
  const [reporting, setReporting] = useState(false);
  const { data, isPending, error, refetch } = useMatch<EmployerMatch>(matchId);

  if (isPending) {
    return (
      <Screen hasHeader>
        <View style={styles.centered}>
          <ActivityIndicator color={colors.accent} />
        </View>
      </Screen>
    );
  }

  if (error || !data) {
    return (
      <Screen hasHeader>
        <EmptyState
          title="Match introuvable"
          description={
            error instanceof Error
              ? error.message
              : "Ce match n'existe plus ou a été fermé."
          }
          action={{ label: 'Réessayer', onPress: () => void refetch() }}
        />
      </Screen>
    );
  }

  // Le serveur choisit la forme selon le role ; si ce n'est pas la forme
  // employeur, c'est que la session a change de compte sous nos pieds.
  if (!isEmployerMatch(data)) return <Redirect href="/matchs" />;

  const candidat = data.candidate;
  const dossier = data.application;

  return (
    <Screen hasHeader>
      <Text variant="hero">{data.job_offer?.title ?? 'Offre supprimée'}</Text>
      <Text variant="small" tone="muted" style={styles.subtitle}>
        Match {formatRelativeFr(data.matched_at)}
      </Text>

      {dossier ? (
        <ReceivedApplicationCard application={dossier} invalidateKey={MATCHES_KEY} />
      ) : (
        <Card>
          <View style={styles.waiting}>
            <Ionicons name="hourglass-outline" size={20} color={colors.accentWarm} />
            <Text variant="bodyStrong" style={styles.waitingText}>
              Attend son dossier
            </Text>
          </View>
          <Text variant="small" tone="muted">
            On l&apos;a prévenu·e par notification et par email. Tant qu&apos;il ou elle
            n&apos;a pas envoyé son dossier, tu vois sa carte — pas son nom complet, ses
            coordonnées ni son CV.
          </Text>
        </Card>
      )}

      {candidat ? (
        <View style={styles.cardArea}>
          {/* La carte du deck, telle quelle : meme presenteur, memes champs.
              Une fois le dossier recu elle reste affichee — competences,
              mobilite et disponibilite ne sont pas dans la candidature. */}
          <CandidateCardFace candidate={candidat} />
        </View>
      ) : (
        <Text variant="small" tone="muted">
          Ce profil a été supprimé depuis le match.
        </Text>
      )}

      <Pressable
        onPress={() => setReporting(true)}
        accessibilityRole="button"
        accessibilityLabel="Signaler ce match"
        style={({ pressed }) => [styles.report, { opacity: pressed ? 0.6 : 1 }]}
      >
        <Ionicons name="flag-outline" size={16} color={colors.textMuted} />
        <Text variant="small" tone="muted">
          Signaler ou bloquer
        </Text>
      </Pressable>

      <ReportSheet
        target={reporting ? { offer_interest_id: data.id } : null}
        context="MATCH"
        label={
          candidat
            ? `${candidat.first_name} ${candidat.last_name_initial ?? ''}`.trim()
            : 'Ce match'
        }
        onClose={() => setReporting(false)}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  centered: { paddingVertical: spacing.xxl, alignItems: 'center' },
  subtitle: { marginBottom: spacing.lg },
  waiting: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  waitingText: { flex: 1 },
  // La face de carte est concue pour remplir une pile : dans une page qui
  // defile il lui faut une hauteur, sinon son `flex: 1` la reduit a rien.
  cardArea: { height: 520, marginTop: spacing.lg, borderRadius: radii.lg },
  report: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.xs,
    paddingVertical: spacing.lg,
  },
});
