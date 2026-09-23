import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { Modal, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Badge } from '@/components/ui/badge';
import { Text } from '@/components/ui/text';
import type { CandidateCard } from '@/lib/api/discover';
import { formatDateFr, formatPeriodFr } from '@/lib/dates';
import {
  AGE_BAND_LABELS,
  CONTRACT_TYPE_LABELS,
  DRIVING_LICENSE_LABELS,
  OFFER_SECTOR_LABELS,
} from '@/lib/labels';
import { useTheme } from '@/theme/theme-provider';
import { fonts, radii, spacing } from '@/theme/typography';

// La carte depliee : tout ce que la pile montre, en entier et defilant.
//
// EXACTEMENT LES MEMES DONNEES que la face de carte — pas une requete de
// plus, pas un champ de plus. C'est la regle d'exposition unique (§4.3) :
// ce que l'employeur voit avant le dossier est fixe par
// CandidateCardPresenter, et « ouvrir la fiche » ne doit pas devenir la
// porte derobee par laquelle une ville ou un telephone finit par passer.
//
// Ce qui change ici, c'est la place : toutes les competences au lieu de six,
// toutes les formations et experiences au lieu de la derniere.

export function CandidateDetailSheet({
  candidate,
  onClose,
}: {
  candidate: CandidateCard;
  onClose: () => void;
}) {
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();

  const nom = [
    candidate.first_name,
    candidate.last_name_initial ? `${candidate.last_name_initial}.` : null,
  ]
    .filter(Boolean)
    .join(' ');

  return (
    <Modal
      visible
      transparent
      animationType="slide"
      onRequestClose={onClose}
      statusBarTranslucent
    >
      <Pressable
        style={styles.backdrop}
        onPress={onClose}
        accessibilityRole="button"
        accessibilityLabel="Fermer la fiche"
      />
      <View
        style={[
          styles.sheet,
          { backgroundColor: colors.surface, borderColor: colors.border },
        ]}
        accessibilityViewIsModal
      >
        <View style={styles.grip}>
          <View style={[styles.gripBar, { backgroundColor: colors.border }]} />
        </View>

        <ScrollView
          contentContainerStyle={[
            styles.content,
            { paddingBottom: insets.bottom + spacing.xxl },
          ]}
        >
          <View style={styles.identity}>
            {candidate.photo_url ? (
              <Image
                source={{ uri: candidate.photo_url }}
                style={styles.portrait}
                contentFit="cover"
                accessibilityLabel=""
              />
            ) : null}
            <View style={styles.identityText}>
              <Text variant="title">{nom || 'Candidat'}</Text>
              {candidate.age_band ? (
                <Text variant="small" tone="muted">
                  {AGE_BAND_LABELS[candidate.age_band]}
                </Text>
              ) : null}
              {candidate.headline ? (
                <Text variant="bodyStrong">{candidate.headline}</Text>
              ) : null}
            </View>
          </View>

          {candidate.pitch ? (
            <Text variant="body" tone="muted" style={styles.pitch}>
              « {candidate.pitch} »
            </Text>
          ) : null}

          <Block title="Ce qu'il ou elle cherche">
            <View style={styles.chips}>
              {candidate.wanted_contract_types.map((type) => (
                <Badge key={type} label={CONTRACT_TYPE_LABELS[type]} tone="accent" />
              ))}
              {candidate.wanted_sectors.map((sector) => (
                <Badge key={sector} label={OFFER_SECTOR_LABELS[sector]} />
              ))}
            </View>
            {candidate.available_from ? (
              <Line
                icon="calendar-outline"
                text={`Disponible dès le ${formatDateFr(candidate.available_from)}`}
              />
            ) : null}
          </Block>

          <Block title="Mobilité">
            {candidate.mobility?.covers_offer ? (
              <Line icon="navigate-outline" text="Sa zone de mobilité couvre ton offre" />
            ) : null}
            <Line
              icon="car-outline"
              text={
                candidate.has_driving_license
                  ? candidate.driving_license_categories
                      .map((category) => DRIVING_LICENSE_LABELS[category])
                      .join('\n') || 'Permis'
                  : 'Pas encore le permis'
              }
            />
            <Line
              icon={
                candidate.has_vehicle
                  ? 'checkmark-circle-outline'
                  : 'close-circle-outline'
              }
              text={candidate.has_vehicle ? 'A un véhicule' : 'Pas de véhicule'}
            />
          </Block>

          {candidate.skills.length > 0 ? (
            <Block title="Compétences">
              <View style={styles.chips}>
                {candidate.skills.map((skill) => (
                  <Badge
                    key={skill.id}
                    label={skill.name}
                    tone={skill.in_common ? 'success' : 'neutral'}
                  />
                ))}
              </View>
            </Block>
          ) : null}

          {candidate.software.length > 0 ? (
            <Block title="Logiciels">
              <View style={styles.chips}>
                {candidate.software.map((software) => (
                  <Badge key={software.id} label={software.name} />
                ))}
              </View>
            </Block>
          ) : null}

          {candidate.languages.length > 0 ? (
            <Block title="Langues">
              {candidate.languages.map((language) => (
                <Line
                  key={language.name}
                  icon="language-outline"
                  text={
                    language.level
                      ? `${language.name} · ${language.level}`
                      : language.name
                  }
                />
              ))}
            </Block>
          ) : null}

          {candidate.educations.length > 0 ? (
            <Block title="Formations">
              {candidate.educations.map((education, index) => (
                <Entry
                  key={`${education.school ?? ''}-${education.degree ?? ''}-${index}`}
                  title={[education.degree, education.field_of_study]
                    .filter(Boolean)
                    .join(' · ')}
                  subtitle={education.school}
                  period={periode(education.start_date, education.end_date)}
                />
              ))}
            </Block>
          ) : null}

          {candidate.experiences.length > 0 ? (
            <Block title="Expériences">
              {candidate.experiences.map((experience, index) => (
                <Entry
                  key={`${experience.company ?? ''}-${experience.title ?? ''}-${index}`}
                  title={experience.title}
                  subtitle={experience.company}
                  period={periode(experience.start_date, experience.end_date)}
                />
              ))}
            </Block>
          ) : null}

          <View style={[styles.note, { borderColor: colors.border }]}>
            <Ionicons name="lock-closed-outline" size={16} color={colors.textMuted} />
            <Text variant="small" tone="muted" style={styles.noteText}>
              {candidate.has_uploaded_cv
                ? 'Son nom complet, ses coordonnées et son CV arrivent avec sa candidature.'
                : 'Son nom complet et ses coordonnées arrivent avec sa candidature.'}
            </Text>
          </View>
        </ScrollView>
      </View>
    </Modal>
  );
}

function periode(start: string | null, end: string | null): string | null {
  return start ? formatPeriodFr(start, end) : null;
}

function Block({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <View style={styles.block}>
      <Text variant="label" tone="muted">
        {title.toUpperCase()}
      </Text>
      {children}
    </View>
  );
}

function Line({ icon, text }: { icon: keyof typeof Ionicons.glyphMap; text: string }) {
  const { colors } = useTheme();

  return (
    <View style={styles.line}>
      <Ionicons name={icon} size={16} color={colors.textMuted} />
      <Text variant="small" style={styles.lineText}>
        {text}
      </Text>
    </View>
  );
}

function Entry({
  title,
  subtitle,
  period,
}: {
  title: string | null;
  subtitle: string | null;
  period: string | null;
}) {
  return (
    <View style={styles.entry}>
      {title ? <Text variant="bodyStrong">{title}</Text> : null}
      {subtitle ? (
        <Text variant="small" tone="muted">
          {subtitle}
        </Text>
      ) : null}
      {period ? (
        <Text variant="small" tone="muted">
          {period}
        </Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  backdrop: { flex: 1, backgroundColor: 'rgba(6, 29, 79, 0.45)' },
  sheet: {
    maxHeight: '88%',
    borderTopLeftRadius: radii.lg,
    borderTopRightRadius: radii.lg,
    borderTopWidth: 1,
  },
  grip: { alignItems: 'center', paddingVertical: spacing.sm },
  gripBar: { width: 44, height: 4, borderRadius: 2 },
  content: { paddingHorizontal: spacing.xl, gap: spacing.lg },
  identity: { flexDirection: 'row', alignItems: 'center', gap: spacing.md },
  identityText: { flex: 1, gap: 2 },
  portrait: { width: 64, height: 64, borderRadius: 32 },
  pitch: { fontStyle: 'italic' },
  block: { gap: spacing.sm },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  line: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.sm },
  lineText: { flex: 1, fontFamily: fonts.bodyMedium },
  entry: { gap: 2 },
  note: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: spacing.sm,
    padding: spacing.md,
    borderRadius: radii.md,
    borderWidth: 1,
  },
  noteText: { flex: 1 },
});
