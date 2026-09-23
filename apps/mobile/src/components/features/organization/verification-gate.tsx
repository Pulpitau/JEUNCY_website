import { Ionicons } from '@expo/vector-icons';
import { VerificationStatus } from '@jeuncy/shared';
import { useRouter } from 'expo-router';
import type { ReactNode } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { Button } from '@/components/ui/button';
import { Text } from '@/components/ui/text';
import { useOrganization } from '@/hooks/use-organization';
import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

// La porte de vérification (MOBILE.md §4.0), cote application.
//
// Sans VERIFIED : ni pile de candidats, ni « Ca m'interesse », ni CVtheque,
// ni dossiers recus. Le serveur le refuse deja de son cote
// (COMPANY_NOT_VERIFIED, 403) — c'est LUI qui fait foi. Cette porte-ci
// n'existe que pour ne pas montrer un ecran qui repondrait 403 a chaque
// geste : elle explique, elle ne protege pas. Ne jamais l'inverser, c'est-a-
// dire ne jamais rendre un ecran sensible parce que le client a lu VERIFIED.
//
// Trois etats, trois messages differents, parce qu'ils appellent trois
// actions differentes : completer sa fiche, attendre, ou corriger un SIRET.

export function VerificationGate({ children }: { children: ReactNode }) {
  const { colors } = useTheme();
  const router = useRouter();
  const { data: organization, isPending, error } = useOrganization();

  if (isPending) {
    return (
      <View style={[styles.centered, { backgroundColor: colors.background }]}>
        <ActivityIndicator color={colors.accent} />
      </View>
    );
  }

  if (error) {
    return (
      <GateCard
        icon="cloud-offline-outline"
        tone="muted"
        title="Impossible de vérifier ton compte"
        description={error.message}
      />
    );
  }

  // Pas encore de fiche : c'est l'etape d'avant, et le SIRET s'y saisit.
  if (!organization) {
    return (
      <GateCard
        icon="business-outline"
        tone="accent"
        title="Complète ta fiche"
        description="Ton SIRET nous permet de vérifier ton entreprise. C'est ce qui autorise l'accès aux profils des candidats, dont des mineurs."
        action={{
          label: 'Renseigner ma fiche',
          onPress: () => router.push('/organisation/informations'),
        }}
      />
    );
  }

  if (organization.verification_status === VerificationStatus.PENDING) {
    return (
      <GateCard
        icon="hourglass-outline"
        tone="warm"
        title="Vérification en cours"
        description={
          organization.verification_note ??
          "On vérifie ton SIRET auprès du registre public. Tant que ce n'est pas fait, les profils des candidats restent fermés."
        }
        action={{
          label: 'Vérifier ma fiche',
          onPress: () => router.push('/organisation/informations'),
        }}
      />
    );
  }

  if (organization.verification_status === VerificationStatus.REJECTED) {
    return (
      <GateCard
        icon="alert-circle-outline"
        tone="danger"
        title="Vérification refusée"
        description={
          organization.verification_note ??
          "Le SIRET renseigné n'a pas pu être validé. Corrige-le sur ta fiche, la vérification repart automatiquement."
        }
        action={{
          label: 'Corriger ma fiche',
          onPress: () => router.push('/organisation/informations'),
        }}
      />
    );
  }

  return <>{children}</>;
}

function GateCard({
  icon,
  tone,
  title,
  description,
  action,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  tone: 'accent' | 'warm' | 'danger' | 'muted';
  title: string;
  description: string;
  action?: { label: string; onPress: () => void };
}) {
  const { colors } = useTheme();
  const teinte = {
    accent: colors.accent,
    warm: colors.accentWarm,
    danger: colors.danger,
    muted: colors.textMuted,
  }[tone];

  return (
    <View style={[styles.centered, { backgroundColor: colors.background }]}>
      <View
        style={[
          styles.card,
          { backgroundColor: colors.surface, borderColor: colors.border },
        ]}
      >
        <Ionicons name={icon} size={36} color={teinte} />
        <Text variant="title" style={styles.centre}>
          {title}
        </Text>
        <Text variant="body" tone="muted" style={styles.centre}>
          {description}
        </Text>
        {action ? (
          <Button label={action.label} variant="secondary" onPress={action.onPress} />
        ) : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: spacing.xl,
  },
  card: {
    alignItems: 'center',
    gap: spacing.md,
    padding: spacing.xl,
    borderRadius: radii.lg,
    borderWidth: 1,
    width: '100%',
  },
  centre: { textAlign: 'center' },
});
