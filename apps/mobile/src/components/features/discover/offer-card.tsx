import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import type { ReactNode } from 'react';
import { StyleSheet, View } from 'react-native';

import { Badge } from '@/components/ui/badge';
import { Text } from '@/components/ui/text';
import {
  EXTERNAL_SOURCE_LABEL,
  formatExternalLocation,
  type ExternalJobOffer,
} from '@/lib/api/external-offers';
import { isCfaOffer, publisherOf, type PublicJobOffer } from '@/lib/api/job-offers';
import { formatDateFr } from '@/lib/dates';
import { formatCompensation } from '@/lib/format-compensation';
import { CONTRACT_TYPE_LABELS, WORK_MODE_LABELS } from '@/lib/labels';
import { palette, signatureGradient } from '@/theme/colors';
import { useTheme } from '@/theme/theme-provider';
import { fonts, radii, spacing } from '@/theme/typography';

// Faces de carte de la pile « Decouvrir ». Deux variantes au contrat visuel
// distinct, pour qu'un candidat sache d'un coup d'oeil ou il postulera :
//
// - offre Jeuncy : bandeau au degrade signature, logo de l'organisation,
//   candidature sur Jeuncy (dossier ou interet) ;
// - offre partenaire (La bonne alternance) : bandeau orange qui annonce la
//   candidature sur le site de l'employeur, pas de logo, source citee
//   (licence Etalab 2.0). Le mot « match » n'y apparait jamais.
//
// Pas encore de photo d'equipe : aucune colonne ne la porte. Pas de badge
// « Permis requis » non plus : le champ n'existe pas sur JobOffer (il
// arrive avec `requires_driving_license`, lot 1).

/** Nombre de lignes de description sur la face : le reste se lit dans la fiche. */
const EXCERPT_LINES = 5;
const MAX_SKILLS = 3;

// ---------------------------------------------------------------------------
// Offre Jeuncy
// ---------------------------------------------------------------------------

export function JeuncyOfferCard({
  offer,
}: {
  offer: PublicJobOffer & { employer_response_days?: number | null };
}) {
  const responseDays = offer.employer_response_days;
  const { colors } = useTheme();
  const publisher = publisherOf(offer);
  const cfa = isCfaOffer(offer);
  const remuneration = formatCompensation(
    offer.compensation_amount,
    offer.compensation_period,
    offer.compensation,
  );
  // Une offre de CFA met en avant le niveau vise, une offre d'entreprise
  // l'experience demandee — comme la liste et la fiche.
  const level = cfa ? offer.diploma_level : offer.experience_level;
  const skills = offer.skills.slice(0, MAX_SKILLS);
  const initial = publisher?.name.trim().charAt(0).toUpperCase() ?? '?';

  return (
    <CardFrame>
      <LinearGradient
        colors={[...signatureGradient]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 0 }}
        style={styles.band}
      >
        <View style={styles.logoTile}>
          {publisher?.logo_url ? (
            <Image
              source={{ uri: publisher.logo_url }}
              style={styles.logo}
              contentFit="contain"
              accessibilityLabel=""
            />
          ) : (
            <Text variant="title" style={{ color: palette.coral }}>
              {initial}
            </Text>
          )}
        </View>
        <View style={styles.bandText}>
          <Text variant="bodyStrong" numberOfLines={1} style={styles.onGradient}>
            {publisher?.name ?? 'Organisation'}
          </Text>
          <View style={styles.pill}>
            <Text variant="label" style={styles.onGradient}>
              {cfa ? 'CFA' : 'Entreprise'}
            </Text>
          </View>
        </View>
      </LinearGradient>

      <View style={styles.body}>
        <Text variant="title" numberOfLines={3}>
          {offer.title}
        </Text>

        <View style={styles.chips}>
          <Badge label={CONTRACT_TYPE_LABELS[offer.contract_type]} tone="accent" />
          {offer.work_mode ? <Badge label={WORK_MODE_LABELS[offer.work_mode]} /> : null}
          {/* Le seul badge qui parle du COMPORTEMENT du recruteur et non de
              son offre. C'est l'argument du produit rendu verifiable avant
              de postuler (MOBILE.md §5). */}
          {responseDays !== undefined ? (
            <Badge
              label={
                responseDays === null
                  ? 'Nouvelle entreprise'
                  : responseDays <= 1
                    ? 'Répond en 24 h'
                    : `Répond en ${responseDays} jours`
              }
              tone={responseDays !== null && responseDays <= 3 ? 'success' : 'neutral'}
            />
          ) : null}
        </View>

        <View style={styles.facts}>
          <Fact icon="location-outline" value={offer.city ?? offer.location} />
          <Fact icon="cash-outline" value={remuneration} />
          <Fact icon="school-outline" value={level} />
          <Fact icon="calendar-outline" value={cfa ? offer.training_rhythm : null} />
        </View>

        <Text
          variant="body"
          tone="muted"
          numberOfLines={EXCERPT_LINES}
          style={styles.excerpt}
        >
          {offer.description}
        </Text>

        {skills.length > 0 ? (
          <View style={styles.chips}>
            {skills.map((skill) => (
              <Badge key={skill.id} label={skill.name} />
            ))}
          </View>
        ) : null}
      </View>

      <View style={[styles.footer, { borderTopColor: colors.border }]}>
        <Ionicons name="heart-outline" size={14} color={colors.accent} />
        <Text variant="small" tone="muted">
          Candidature sur Jeuncy
        </Text>
      </View>
    </CardFrame>
  );
}

// ---------------------------------------------------------------------------
// Offre partenaire (La bonne alternance)
// ---------------------------------------------------------------------------

export function PartnerOfferCard({ offer }: { offer: ExternalJobOffer }) {
  const { colors } = useTheme();
  const location = formatExternalLocation(offer);
  const duration =
    offer.contract_duration_months !== null
      ? `${offer.contract_duration_months} mois`
      : null;
  const start = offer.contract_start
    ? `Début ${formatDateFr(offer.contract_start)}`
    : null;

  return (
    <CardFrame>
      <View style={[styles.band, { backgroundColor: colors.accentWarm }]}>
        <Ionicons name="open-outline" size={22} color={palette.white} />
        <View style={styles.bandText}>
          <Text variant="label" style={styles.onGradient}>
            Offre partenaire
          </Text>
          <Text variant="small" style={styles.onGradient} numberOfLines={2}>
            Candidature sur le site de l&apos;employeur
          </Text>
        </View>
      </View>

      <View style={styles.body}>
        <View style={styles.employer}>
          <Ionicons name="business-outline" size={16} color={colors.textMuted} />
          <Text variant="bodyStrong" numberOfLines={1} style={styles.employerName}>
            {offer.company_name ?? 'Employeur non communiqué'}
          </Text>
        </View>

        <Text variant="title" numberOfLines={3}>
          {offer.title}
        </Text>

        <View style={styles.chips}>
          <Badge label="Alternance" tone="warm" />
          {offer.work_mode ? <Badge label={WORK_MODE_LABELS[offer.work_mode]} /> : null}
        </View>

        <View style={styles.facts}>
          <Fact icon="location-outline" value={location} />
          <Fact icon="calendar-outline" value={start} />
          <Fact icon="time-outline" value={duration} />
          <Fact icon="school-outline" value={offer.target_diploma_label} />
        </View>

        <Text
          variant="body"
          tone="muted"
          numberOfLines={EXCERPT_LINES}
          style={styles.excerpt}
        >
          {offer.description}
        </Text>
      </View>

      <View style={[styles.footer, { borderTopColor: colors.border }]}>
        <Ionicons name="link-outline" size={14} color={colors.textMuted} />
        <Text variant="small" tone="muted">
          Source : {EXTERNAL_SOURCE_LABEL}
        </Text>
      </View>
    </CardFrame>
  );
}

// ---------------------------------------------------------------------------
// Elements communs
// ---------------------------------------------------------------------------

// Cadre de la carte : occupe toute la zone que la pile lui donne, coupe ce
// qui deborde (la face est un apercu, pas la fiche complete).
function CardFrame({ children }: { children: ReactNode }) {
  const { colors, scheme } = useTheme();

  // Deux vues et non une : sur iOS, `overflow: 'hidden'` coupe aussi l'ombre
  // de la vue qui le porte. L'ombre est donc sur l'enveloppe, le decoupage
  // sur la vue interieure. L'enveloppe a la couleur de la carte : sans fond
  // opaque, iOS ne peut pas deduire le trace de l'ombre et la calcule pixel
  // par pixel a chaque image — couteux pendant un glissement.
  return (
    <View
      style={[
        styles.shadow,
        {
          backgroundColor: colors.surface,
          // L'ombre ne se voit pas sur le fond bleu nuit ; elle y est donc
          // plus marquee, ce qui detache la carte de tete des suivantes.
          shadowOpacity: scheme === 'dark' ? 0.45 : 0.12,
        },
      ]}
    >
      <View
        style={[
          styles.frame,
          { backgroundColor: colors.surface, borderColor: colors.border },
        ]}
      >
        {children}
      </View>
    </View>
  );
}

function Fact({
  icon,
  value,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  value: string | null | undefined;
}) {
  const { colors } = useTheme();
  if (!value) return null;

  return (
    <View style={styles.fact}>
      <Ionicons name={icon} size={16} color={colors.textMuted} />
      <Text variant="small" numberOfLines={1} style={styles.factText}>
        {value}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  shadow: {
    flex: 1,
    borderRadius: radii.lg,
    shadowColor: palette.navy,
    shadowOffset: { width: 0, height: 6 },
    shadowRadius: 14,
    elevation: 6,
  },
  frame: {
    flex: 1,
    borderRadius: radii.lg,
    borderWidth: 1,
    overflow: 'hidden',
  },
  band: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
  },
  bandText: { flex: 1, gap: spacing.xs },
  onGradient: { color: palette.white },
  logoTile: {
    width: 48,
    height: 48,
    borderRadius: radii.md,
    backgroundColor: palette.white,
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
  },
  logo: { width: 44, height: 44 },
  pill: {
    alignSelf: 'flex-start',
    paddingHorizontal: spacing.sm,
    paddingVertical: 2,
    borderRadius: radii.pill,
    backgroundColor: 'rgba(255, 255, 255, 0.22)',
  },
  body: {
    flex: 1,
    padding: spacing.lg,
    gap: spacing.md,
    overflow: 'hidden',
  },
  employer: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  employerName: { flex: 1 },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  facts: { gap: spacing.xs },
  fact: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  factText: { flex: 1, fontFamily: fonts.bodyMedium },
  excerpt: { flexShrink: 1 },
  footer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.sm,
    borderTopWidth: 1,
  },
});
