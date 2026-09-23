import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import type { ReactNode } from 'react';
import { StyleSheet, View } from 'react-native';

import { Badge } from '@/components/ui/badge';
import { Text } from '@/components/ui/text';
import type {
  CandidateCard as Card,
  CardEducation,
  CardExperience,
} from '@/lib/api/discover';
import { formatDateFr } from '@/lib/dates';
import {
  AGE_BAND_LABELS,
  CONTRACT_TYPE_LABELS,
  DRIVING_LICENSE_LABELS,
  OFFER_SECTOR_LABELS,
} from '@/lib/labels';
import { palette } from '@/theme/colors';
import { useTheme } from '@/theme/theme-provider';
import { fonts, radii, spacing } from '@/theme/typography';

// Face de carte du deck employeur (MOBILE.md §4.1).
//
// TEXTE D'ABORD, ET L'ORDRE EST LA DECISION. Ce que le jeune cherche vient
// avant qui il est ; son portrait, quand il l'a autorise, n'arrive qu'en
// vignette de fin. Un recruteur qui parcourt une pile juge les trois
// premieres lignes : les remplir d'un visage plutot que d'un projet, sur des
// cartes de mineurs, c'est le produit qui aurait choisi a sa place.
//
// CE QUI N'EST PAS LA EST AUSSI UNE DECISION (§4.3) : pas de nom complet,
// pas de ville ni d'adresse, pas d'email, pas de telephone, pas de date de
// naissance, pas de CV. Et surtout AUCUNE DISTANCE — le lieu de residence
// est un critere discriminatoire (L1132-1) ; la carte dit seulement que les
// deux rayons se couvrent, ce que le serveur a deja verifie.
//
// N'ajoute rien ici sans l'ajouter a CandidateCardPresenter : un champ que
// l'API n'envoie pas ne s'obtient pas en allant le chercher ailleurs.

const MAX_SKILLS = 6;
const MAX_SOFTWARE = 4;

export function CandidateCardFace({
  candidate,
  /** Vignette de fin ; masquee dans un detail qui affiche deja le portrait plus haut. */
  showPhoto = true,
}: {
  candidate: Card;
  showPhoto?: boolean;
}) {
  const { colors } = useTheme();

  const nom = [
    candidate.first_name,
    candidate.last_name_initial ? `${candidate.last_name_initial}.` : null,
  ]
    .filter(Boolean)
    .join(' ');

  const cherche = [
    ...candidate.wanted_contract_types.map((type) => CONTRACT_TYPE_LABELS[type]),
    ...candidate.wanted_sectors.map((sector) => OFFER_SECTOR_LABELS[sector]),
  ];

  const competences = candidate.skills.slice(0, MAX_SKILLS);
  const logiciels = candidate.software.slice(0, MAX_SOFTWARE);
  const formation = candidate.educations[0];
  const experience = candidate.experiences[0];

  return (
    <CardFrame>
      <View style={styles.body}>
        {/* 1. Ce qu'il cherche. */}
        {cherche.length > 0 ? (
          <View style={styles.chips}>
            {cherche.map((label) => (
              <Badge key={label} label={label} tone="accent" />
            ))}
          </View>
        ) : null}

        {/* 2 et 3. Prenom + initiale, tranche d'age. */}
        <View style={styles.identity}>
          <Text variant="title" numberOfLines={1}>
            {nom || 'Candidat'}
          </Text>
          {candidate.age_band ? (
            <Text variant="small" tone="muted">
              {AGE_BAND_LABELS[candidate.age_band]}
            </Text>
          ) : null}
        </View>

        {/* 4. Titre du profil. */}
        {candidate.headline ? (
          <Text variant="bodyStrong" numberOfLines={2}>
            {candidate.headline}
          </Text>
        ) : null}

        {/* 5. Mobilite : jamais une ville, jamais une distance. */}
        {candidate.mobility?.covers_offer ? (
          <View style={[styles.mobility, { backgroundColor: colors.surfaceMuted }]}>
            <Ionicons name="navigate-outline" size={16} color={colors.success} />
            <Text variant="small" style={styles.mobilityText}>
              Sa zone de mobilité couvre ton offre
            </Text>
          </View>
        ) : null}

        {/* 6. Permis, vehicule, disponibilite. */}
        <View style={styles.facts}>
          <Fact icon="car-outline" value={permisLabel(candidate)} />
          <Fact
            icon="calendar-outline"
            value={
              candidate.available_from
                ? `Disponible dès le ${formatDateFr(candidate.available_from)}`
                : null
            }
          />
        </View>

        {/* 7. Competences (communes d'abord), logiciels, formation, experience. */}
        {competences.length > 0 ? (
          <View style={styles.chips}>
            {competences.map((skill) => (
              <Badge
                key={skill.id}
                label={skill.name}
                tone={skill.in_common ? 'success' : 'neutral'}
              />
            ))}
          </View>
        ) : null}

        {logiciels.length > 0 ? (
          <Fact
            icon="desktop-outline"
            value={logiciels.map((software) => software.name).join(' · ')}
          />
        ) : null}

        {formation ? (
          <Fact icon="school-outline" value={formationLabel(formation)} />
        ) : null}
        {experience ? (
          <Fact icon="briefcase-outline" value={experienceLabel(experience)} />
        ) : null}

        {/* 8. La phrase du candidat. */}
        {candidate.pitch ? (
          <Text variant="body" tone="muted" numberOfLines={3} style={styles.pitch}>
            « {candidate.pitch} »
          </Text>
        ) : null}
      </View>

      {/* 9. Vignette de fin, seulement si le candidat l'a autorisee. */}
      <View style={[styles.footer, { borderTopColor: colors.border }]}>
        {showPhoto && candidate.photo_url ? (
          <Image
            source={{ uri: candidate.photo_url }}
            style={styles.thumbnail}
            contentFit="cover"
            accessibilityLabel=""
          />
        ) : (
          <View
            style={[
              styles.thumbnail,
              styles.initialTile,
              { backgroundColor: colors.surfaceMuted },
            ]}
          >
            <Text variant="label" tone="muted">
              {candidate.first_name.trim().charAt(0).toUpperCase() || '?'}
            </Text>
          </View>
        )}
        <Text variant="small" tone="muted" style={styles.footerText}>
          {candidate.has_uploaded_cv
            ? 'CV disponible après candidature'
            : 'Profil Jeuncy'}
        </Text>
      </View>
    </CardFrame>
  );
}

/**
 * « Permis B, véhicule » ou « Pas encore le permis ».
 *
 * Le dire explicitement plutot que de masquer la ligne : sur un poste avec
 * tournee, l'absence de permis est une information utile tout de suite, et
 * une ligne vide se lit comme un profil incomplet.
 */
function permisLabel(candidate: Card): string {
  if (!candidate.has_driving_license) return 'Pas encore le permis';

  const categories = candidate.driving_license_categories
    // Le libelle long explique la categorie ; sur une carte, la lettre suffit.
    .map((category) => DRIVING_LICENSE_LABELS[category].split(' — ')[0])
    .join(', ');

  const permis = categories ? `Permis ${categories}` : 'Permis';

  return candidate.has_vehicle ? `${permis} · véhicule` : permis;
}

function formationLabel(education: CardEducation): string | null {
  const parts = [education.degree, education.field_of_study, education.school].filter(
    Boolean,
  );

  return parts.length > 0 ? parts.join(' · ') : null;
}

function experienceLabel(experience: CardExperience): string | null {
  const parts = [experience.title, experience.company].filter(Boolean);
  if (parts.length === 0) return null;

  const annee = experience.start_date?.slice(0, 4);

  return annee ? `${parts.join(' · ')} (${annee})` : parts.join(' · ');
}

// ---------------------------------------------------------------------------

/**
 * Meme cadre que les cartes d'offre : deux vues, l'ombre dehors et le
 * decoupage dedans, parce qu'`overflow: 'hidden'` coupe aussi l'ombre sur
 * iOS. Le fond opaque evite a iOS de recalculer le trace a chaque image
 * pendant un glissement.
 */
function CardFrame({ children }: { children: ReactNode }) {
  const { colors, scheme } = useTheme();

  return (
    <View
      style={[
        styles.shadow,
        {
          backgroundColor: colors.surface,
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
      <Text variant="small" numberOfLines={2} style={styles.factText}>
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
  body: {
    flex: 1,
    padding: spacing.lg,
    gap: spacing.md,
    overflow: 'hidden',
  },
  identity: { gap: 2 },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  mobility: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    borderRadius: radii.md,
  },
  mobilityText: { flex: 1, fontFamily: fonts.bodyMedium },
  facts: { gap: spacing.xs },
  fact: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.sm },
  factText: { flex: 1, fontFamily: fonts.bodyMedium },
  pitch: { flexShrink: 1, fontStyle: 'italic' },
  footer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.sm,
    borderTopWidth: 1,
  },
  footerText: { flex: 1 },
  thumbnail: { width: 32, height: 32, borderRadius: 16 },
  initialTile: { alignItems: 'center', justifyContent: 'center' },
});
