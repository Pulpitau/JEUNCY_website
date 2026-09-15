import { Ionicons } from '@expo/vector-icons';
import { useState } from 'react';
import { FlatList, Modal, Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

import { Text } from './text';

export interface SelectOption<T extends string> {
  value: T;
  label: string;
}

export interface SelectFieldProps<T extends string> {
  label: string;
  options: readonly SelectOption<T>[];
  value: T | null;
  onChange: (value: T | null) => void;
  placeholder?: string;
  error?: string;
  /** Propose une ligne « Aucun » qui remet la valeur a null. */
  clearable?: boolean;
}

// Liste deroulante a la maniere d'iOS : un champ qui ouvre une feuille en bas
// de l'ecran avec les choix. Pour trois ou quatre options, ChoiceGroup (tout
// visible) vaut mieux ; au-dela, ce composant evite un mur de puces.
export function SelectField<T extends string>({
  label,
  options,
  value,
  onChange,
  placeholder = 'Choisir',
  error,
  clearable = false,
}: SelectFieldProps<T>) {
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();
  const [open, setOpen] = useState(false);
  const selected = options.find((o) => o.value === value) ?? null;

  const choisir = (next: T | null) => {
    onChange(next);
    setOpen(false);
  };

  return (
    <View style={styles.wrapper}>
      <Text variant="label" tone="muted">
        {label}
      </Text>
      <Pressable
        onPress={() => setOpen(true)}
        accessibilityRole="button"
        accessibilityLabel={label}
        accessibilityValue={{ text: selected?.label ?? placeholder }}
        style={[
          styles.input,
          {
            backgroundColor: colors.surface,
            borderColor: error ? colors.danger : colors.border,
          },
        ]}
      >
        <Text variant="body" tone={selected ? 'default' : 'muted'} style={styles.value}>
          {selected?.label ?? placeholder}
        </Text>
        <Ionicons name="chevron-down" size={18} color={colors.textMuted} />
      </Pressable>
      {error ? (
        <Text variant="small" tone="danger" accessibilityLiveRegion="polite" role="alert">
          {error}
        </Text>
      ) : null}

      <Modal
        visible={open}
        transparent
        animationType="slide"
        onRequestClose={() => setOpen(false)}
      >
        <Pressable
          style={styles.backdrop}
          onPress={() => setOpen(false)}
          accessibilityRole="button"
          accessibilityLabel="Fermer"
        />
        <View
          style={[
            styles.sheet,
            {
              backgroundColor: colors.surface,
              paddingBottom: insets.bottom + spacing.lg,
            },
          ]}
        >
          <Text variant="sectionTitle" style={styles.sheetTitle}>
            {label}
          </Text>
          <FlatList
            data={clearable ? [null, ...options] : [...options]}
            keyExtractor={(item) => item?.value ?? '__none__'}
            renderItem={({ item }) => {
              const actif = (item?.value ?? null) === value;

              return (
                <Pressable
                  onPress={() => choisir(item?.value ?? null)}
                  accessibilityRole="radio"
                  accessibilityState={{ selected: actif, checked: actif }}
                  style={[styles.option, { borderBottomColor: colors.border }]}
                >
                  <Text
                    variant="body"
                    tone={actif ? 'accent' : item ? 'default' : 'muted'}
                    style={styles.value}
                  >
                    {item?.label ?? 'Aucun'}
                  </Text>
                  {actif ? (
                    <Ionicons name="checkmark" size={20} color={colors.accent} />
                  ) : null}
                </Pressable>
              );
            }}
            style={styles.list}
          />
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: { gap: spacing.xs },
  input: {
    minHeight: 50,
    paddingHorizontal: spacing.lg,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderWidth: 1,
    borderRadius: radii.md,
  },
  value: { flex: 1 },
  backdrop: { flex: 1, backgroundColor: 'rgba(0, 0, 0, 0.4)' },
  sheet: {
    maxHeight: '70%',
    borderTopLeftRadius: radii.lg,
    borderTopRightRadius: radii.lg,
    paddingTop: spacing.lg,
  },
  sheetTitle: { paddingHorizontal: spacing.xl, marginBottom: spacing.sm },
  list: { flexGrow: 0 },
  option: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    paddingHorizontal: spacing.xl,
    paddingVertical: spacing.md,
    minHeight: 48,
    borderBottomWidth: 1,
  },
});
