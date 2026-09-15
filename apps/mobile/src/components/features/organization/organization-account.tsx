import { UserRole } from '@jeuncy/shared';
import { useMutation } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { ActivityIndicator, Alert, StyleSheet, Switch, View } from 'react-native';

import { BrandHeader } from '@/components/brand-header';
import { AvatarUpload } from '@/components/ui/avatar-upload';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Screen } from '@/components/ui/screen';
import { Section } from '@/components/ui/section';
import { Text } from '@/components/ui/text';
import {
  useInvalidateOrganization,
  useOrganization,
  useOrganizationRole,
} from '@/hooks/use-organization';
import { ApiError } from '@/lib/api/client';
import {
  removeOrganizationLogo,
  updateOrganization,
  uploadOrganizationLogo,
  type Organization,
} from '@/lib/api/organization';
import { WORK_MODE_LABELS } from '@/lib/labels';
import { useTheme } from '@/theme/theme-provider';
import { spacing } from '@/theme/typography';

export interface OrganizationAccountProps {
  email: string;
  /** Pied de page commun (confidentialite, apparence, deconnexion). */
  footer: React.ReactNode;
}

// Onglet « Compte » d'une entreprise ou d'un CFA : la fiche de l'organisation
// (equivalent de /organization sur le site), avec logo, presence dans
// l'annuaire public, et l'acces au formulaire complet.
export function OrganizationAccount({ email, footer }: OrganizationAccountProps) {
  const router = useRouter();
  const { colors } = useTheme();
  const role = useOrganizationRole();
  const organization = useOrganization();
  const isCfa = role === UserRole.CFA;
  const label = isCfa ? 'CFA' : 'entreprise';

  if (organization.isPending) {
    return (
      <Screen scroll={false} contentStyle={styles.centered}>
        <ActivityIndicator color={colors.accent} />
      </Screen>
    );
  }

  if (organization.isError) {
    return (
      <Screen>
        <EmptyState
          title="Impossible de charger ta fiche"
          description={organization.error.message}
          action={{ label: 'Réessayer', onPress: () => void organization.refetch() }}
        />
        {footer}
      </Screen>
    );
  }

  if (organization.data === null) {
    return (
      <Screen>
        <BrandHeader title={isCfa ? 'Ton CFA' : 'Ton entreprise'} subtitle={email} />
        <Card>
          <Text variant="sectionTitle">Crée ta fiche</Text>
          <Text variant="small" tone="muted">
            Le nom suffit pour commencer. C&apos;est cette fiche que les candidats verront
            sur tes offres.
          </Text>
          <Button
            label="Créer ma fiche"
            onPress={() => router.push('/organisation/informations')}
          />
        </Card>
        {footer}
      </Screen>
    );
  }

  return (
    <OrganizationSummary organization={organization.data} label={label} footer={footer} />
  );
}

function OrganizationSummary({
  organization,
  label,
  footer,
}: {
  organization: Organization;
  label: string;
  footer: React.ReactNode;
}) {
  const router = useRouter();
  const role = useOrganizationRole();
  const invalidate = useInvalidateOrganization();
  const { colors } = useTheme();

  const erreur = (titre: string) => (error: unknown) =>
    Alert.alert(
      titre,
      error instanceof ApiError ? error.message : 'Réessaie dans un instant.',
    );

  const logoUpload = useMutation({
    mutationFn: (file: Parameters<typeof uploadOrganizationLogo>[1]) =>
      uploadOrganizationLogo(role as NonNullable<typeof role>, file),
    onSuccess: () => void invalidate(),
    onError: erreur('Logo non enregistré'),
  });

  const logoRemove = useMutation({
    mutationFn: () => removeOrganizationLogo(role as NonNullable<typeof role>),
    onSuccess: () => void invalidate(),
    onError: erreur('Suppression impossible'),
  });

  const visibility = useMutation({
    mutationFn: (is_public: boolean) =>
      updateOrganization(role as NonNullable<typeof role>, { is_public }),
    onSuccess: () => void invalidate(),
    onError: erreur('Réglage non enregistré'),
  });

  const mode =
    'work_mode' in organization ? organization.work_mode : organization.training_mode;
  const lieu = [organization.city, organization.postal_code].filter(Boolean).join(' ');

  return (
    <Screen>
      <View style={styles.identity}>
        <AvatarUpload
          imageUrl={organization.logo_url}
          fallback={organization.name.trim().charAt(0).toUpperCase()}
          onUpload={(file) => logoUpload.mutate(file)}
          onRemove={() => logoRemove.mutate()}
          busy={logoUpload.isPending || logoRemove.isPending}
          shape="rounded"
          labels={{ title: 'Logo', add: 'Ajouter un logo', change: 'Changer le logo' }}
        />
        <View style={styles.identityText}>
          <Text variant="title">{organization.name}</Text>
          {lieu ? (
            <Text variant="small" tone="muted">
              {lieu}
            </Text>
          ) : null}
          {mode ? (
            <Text variant="small" tone="muted">
              {WORK_MODE_LABELS[mode]}
            </Text>
          ) : null}
        </View>
      </View>
      <Button
        label="Modifier ma fiche"
        variant="secondary"
        onPress={() => router.push('/organisation/informations')}
      />

      {organization.description ? (
        <Section title="Présentation">
          <Text variant="body" tone="muted">
            {organization.description}
          </Text>
        </Section>
      ) : null}

      <Section title="Annuaire public">
        <Text variant="small" tone="muted">
          Ta fiche apparaît dans l&apos;annuaire des {label}s sur jeuncy.com, consultable
          par tous les visiteurs. Tes offres, elles, restent visibles quoi qu&apos;il
          arrive.
        </Text>
        <View style={styles.switchRow}>
          <Text variant="bodyStrong" style={styles.switchLabel}>
            Figurer dans l&apos;annuaire
          </Text>
          <Switch
            value={organization.is_public}
            onValueChange={(value) => visibility.mutate(value)}
            disabled={visibility.isPending}
            trackColor={{ true: colors.accent, false: colors.border }}
            accessibilityLabel="Figurer dans l'annuaire public"
          />
        </View>
      </Section>

      {footer}
    </Screen>
  );
}

const styles = StyleSheet.create({
  centered: { alignItems: 'center', justifyContent: 'center' },
  identity: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.lg,
    marginBottom: spacing.lg,
  },
  identityText: { flex: 1, gap: spacing.xs },
  switchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    marginTop: spacing.sm,
  },
  switchLabel: { flex: 1 },
});
