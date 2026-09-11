import { Ionicons } from '@expo/vector-icons';
import { Pressable, StyleSheet, View } from 'react-native';

import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

import { Text } from './text';

export interface SectionProps {
  title: string;
  /** Action en tete de section : « Ajouter », « Modifier »... */
  action?: { label: string; onPress: () => void };
  children: React.ReactNode;
}

// Bloc du profil : un titre, une action a droite, un contenu. Le profil en
// compte huit ; sans ce composant chaque ecran reinventerait l'alignement.
export function Section({ title, action, children }: SectionProps) {
  return (
    <View style={styles.section}>
      <View style={styles.header}>
        <Text variant="sectionTitle" style={styles.title}>
          {title}
        </Text>
        {action ? (
          <Pressable
            onPress={action.onPress}
            accessibilityRole="button"
            hitSlop={spacing.sm}
          >
            <Text variant="bodyStrong" tone="accent">
              {action.label}
            </Text>
          </Pressable>
        ) : null}
      </View>
      {children}
    </View>
  );
}

export interface ItemRowProps {
  title: string;
  subtitle?: string | null;
  detail?: string | null;
  /** Un tap ouvre l'edition ; sans onPress, la ligne est purement informative. */
  onPress?: () => void;
  /** Bouton de suppression a droite, avec confirmation a la charge de l'appelant. */
  onDelete?: () => void;
}

// Ligne d'une liste du profil (une experience, une formation, une langue).
export function ItemRow({ title, subtitle, detail, onPress, onDelete }: ItemRowProps) {
  const { colors } = useTheme();

  const contenu = (
    <View style={styles.rowText}>
      <Text variant="bodyStrong">{title}</Text>
      {subtitle ? (
        <Text variant="small" tone="muted">
          {subtitle}
        </Text>
      ) : null}
      {detail ? (
        <Text variant="small" tone="muted">
          {detail}
        </Text>
      ) : null}
    </View>
  );

  return (
    <View
      style={[
        styles.row,
        { backgroundColor: colors.surface, borderColor: colors.border },
      ]}
    >
      {onPress ? (
        <Pressable
          onPress={onPress}
          accessibilityRole="button"
          accessibilityLabel={`Modifier ${title}`}
          style={styles.rowPressable}
        >
          {contenu}
          <Ionicons name="chevron-forward" size={18} color={colors.textMuted} />
        </Pressable>
      ) : (
        <View style={styles.rowPressable}>{contenu}</View>
      )}
      {onDelete ? (
        <Pressable
          onPress={onDelete}
          accessibilityRole="button"
          accessibilityLabel={`Supprimer ${title}`}
          hitSlop={spacing.sm}
          style={styles.delete}
        >
          <Ionicons name="trash-outline" size={20} color={colors.danger} />
        </Pressable>
      ) : null}
    </View>
  );
}

// Texte vide d'une section sans contenu, toujours suivi d'une action dans
// l'en-tete : le candidat sait quoi faire.
export function SectionEmpty({ children }: { children: string }) {
  return (
    <Text variant="small" tone="muted">
      {children}
    </Text>
  );
}

const styles = StyleSheet.create({
  section: { gap: spacing.sm, marginTop: spacing.xl },
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  title: { flex: 1 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderRadius: radii.md,
  },
  rowPressable: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    padding: spacing.md,
    gap: spacing.sm,
  },
  rowText: { flex: 1, gap: 2 },
  delete: { padding: spacing.md },
});
