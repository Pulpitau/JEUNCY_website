import { Ionicons } from '@expo/vector-icons';
import { ApplicationStatus } from '@jeuncy/shared';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Alert, Pressable, StyleSheet, View } from 'react-native';

import { statusTone } from '@/components/features/applications/status';
import { openPdf } from '@/components/features/profile/cv-section';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Text } from '@/components/ui/text';
import { MATCHES_KEY } from '@/hooks/use-matches';
import {
  updateApplicationStatus,
  type EmployerApplicationStatus,
  type ReceivedApplication,
} from '@/lib/api/applications';
import { formatDateFr } from '@/lib/dates';
import { APPLICATION_STATUS_LABELS } from '@/lib/labels';
import { formatRelativeFr } from '@/lib/relative-time';
import { useTheme } from '@/theme/theme-provider';
import { fonts, radii, spacing } from '@/theme/typography';

// Le dossier recu : le SEUL endroit du produit ou l'employeur voit le nom
// complet, le telephone, l'email et le CV d'un candidat.
//
// C'est le candidat qui a ouvert cette porte en envoyant son dossier — pas
// un match, pas un abonnement, pas une recherche. Le composant est partage
// entre le detail d'un match et « Candidatures recues » pour qu'il n'existe
// qu'une seule facon d'afficher ces donnees-la : deux ecrans, deux
// implementations, et l'un des deux finirait par montrer autre chose.

/** Statuts posables, dans l'ordre ou un recrutement avance. */
const STATUSES: readonly EmployerApplicationStatus[] = [
  ApplicationStatus.SEEN,
  ApplicationStatus.INTERVIEW,
  ApplicationStatus.ACCEPTED,
  ApplicationStatus.REJECTED,
];

export function ReceivedApplicationCard({
  application,
  /** Clé de la liste à rafraîchir après un changement de statut. */
  invalidateKey,
}: {
  application: ReceivedApplication;
  invalidateKey: readonly unknown[];
}) {
  const { colors } = useTheme();
  const queryClient = useQueryClient();

  const profile = application.candidate_profile;
  const nom = `${profile.first_name} ${profile.last_name}`.trim();
  const cvUrl = application.generated_cv?.file_url ?? application.cv_file_url;

  const mutation = useMutation({
    mutationFn: (status: EmployerApplicationStatus) =>
      updateApplicationStatus(application.id, status),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: invalidateKey });
      // Le statut apparait aussi sur la carte de match : la laisser perimee
      // afficherait deux verites differentes dans la meme application.
      void queryClient.invalidateQueries({ queryKey: MATCHES_KEY });
    },
    onError: (error: Error) => {
      Alert.alert('Statut non enregistré', error.message);
    },
  });

  return (
    <Card>
      <View style={styles.head}>
        <View style={styles.headText}>
          <Text variant="bodyStrong">{nom}</Text>
          <Text variant="small" tone="muted">
            Dossier reçu {formatRelativeFr(application.created_at)}
          </Text>
        </View>
        <Badge
          label={APPLICATION_STATUS_LABELS[application.status]}
          tone={statusTone(application.status)}
        />
      </View>

      <View style={styles.contact}>
        <ContactLine icon="mail-outline" value={profile.user.email} />
        <ContactLine
          icon="call-outline"
          value={application.contact_phone ?? profile.phone}
        />
        {profile.birth_date ? (
          <ContactLine
            icon="balloon-outline"
            value={`Né·e le ${formatDateFr(profile.birth_date)}`}
          />
        ) : null}
      </View>

      {application.cover_letter ? (
        <View style={[styles.letter, { backgroundColor: colors.surfaceMuted }]}>
          <Text variant="small">{application.cover_letter}</Text>
        </View>
      ) : null}

      {cvUrl ? (
        <Pressable
          onPress={() => void openPdf(cvUrl)}
          accessibilityRole="button"
          accessibilityLabel="Ouvrir le CV"
          style={({ pressed }) => [
            styles.cv,
            { borderColor: colors.border, opacity: pressed ? 0.7 : 1 },
          ]}
        >
          <Ionicons name="document-text-outline" size={18} color={colors.accent} />
          <Text variant="bodyStrong" tone="accent" style={styles.cvText}>
            Ouvrir le CV
          </Text>
          <Ionicons name="open-outline" size={16} color={colors.accent} />
        </Pressable>
      ) : (
        <Text variant="small" tone="muted">
          Aucun CV joint à cette candidature.
        </Text>
      )}

      <Text variant="label" tone="muted" style={styles.statusLabel}>
        OÙ EN EST CETTE CANDIDATURE ?
      </Text>
      <View style={styles.statuses}>
        {STATUSES.map((status) => {
          const actif = application.status === status;

          return (
            <Pressable
              key={status}
              onPress={() => mutation.mutate(status)}
              disabled={actif || mutation.isPending}
              accessibilityRole="radio"
              accessibilityState={{ selected: actif, disabled: actif }}
              style={({ pressed }) => [
                styles.status,
                {
                  backgroundColor: actif ? colors.surfaceMuted : 'transparent',
                  borderColor: actif ? colors.accent : colors.border,
                  opacity: pressed ? 0.7 : 1,
                },
              ]}
            >
              <Text variant="label" tone={actif ? 'accent' : 'muted'}>
                {APPLICATION_STATUS_LABELS[status]}
              </Text>
            </Pressable>
          );
        })}
      </View>

      {application.responded_at === null ? (
        <Text variant="small" tone="muted">
          Le candidat attend ta réponse. Un statut, même « Refusée », vaut mieux que le
          silence.
        </Text>
      ) : null}
    </Card>
  );
}

function ContactLine({
  icon,
  value,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  value: string | null;
}) {
  const { colors } = useTheme();
  if (!value) return null;

  return (
    <View style={styles.contactLine}>
      <Ionicons name={icon} size={16} color={colors.textMuted} />
      <Text variant="small" style={styles.contactText} selectable>
        {value}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  head: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.sm },
  headText: { flex: 1, gap: 2 },
  contact: { gap: spacing.xs },
  contactLine: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  contactText: { flex: 1, fontFamily: fonts.bodyMedium },
  letter: { padding: spacing.md, borderRadius: radii.md },
  cv: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    padding: spacing.md,
    borderRadius: radii.md,
    borderWidth: 1,
  },
  cvText: { flex: 1 },
  statusLabel: { marginTop: spacing.xs },
  statuses: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  status: {
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.xs,
    borderRadius: radii.pill,
    borderWidth: 1,
  },
});
