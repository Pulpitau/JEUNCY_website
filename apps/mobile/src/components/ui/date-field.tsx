import DateTimePicker, {
  type DateTimePickerEvent,
} from '@react-native-community/datetimepicker';
import { useState } from 'react';
import { Modal, Platform, Pressable, StyleSheet, View } from 'react-native';

import { formatDateFr, fromIsoDate, toIsoDate } from '@/lib/dates';
import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

import { Button } from './button';
import { Text } from './text';

export interface DateFieldProps {
  label: string;
  /** Date ISO « AAAA-MM-JJ », ou null si vide. */
  value: string | null;
  onChange: (value: string | null) => void;
  error?: string;
  placeholder?: string;
  minimumDate?: Date;
  maximumDate?: Date;
  /** Affiche un bouton pour vider la date (fin d'experience « en cours », par exemple). */
  clearable?: boolean;
  clearLabel?: string;
}

// Champ date qui ouvre le selecteur natif. Deux comportements selon la
// plateforme, parce que les deux systemes ne proposent pas la meme chose :
// iOS affiche une roue que l'on valide, Android une boite de dialogue qui se
// ferme seule au choix. Saisir une date au clavier (« 12/03/2004 ») aurait
// ete plus simple a coder et bien plus penible a utiliser sur un telephone.
export function DateField({
  label,
  value,
  onChange,
  error,
  placeholder = 'Choisir une date',
  minimumDate,
  maximumDate,
  clearable = false,
  clearLabel = 'Effacer',
}: DateFieldProps) {
  const { colors, scheme } = useTheme();
  const [open, setOpen] = useState(false);
  // Sur iOS la roue modifie une valeur temporaire, validee au bouton : fermer
  // sans valider ne doit rien changer.
  const [draft, setDraft] = useState<Date>(() =>
    value ? fromIsoDate(value) : defaultDate(),
  );

  function defaultDate(): Date {
    // Sans valeur, la roue s'ouvre sur la borne haute (« aujourd'hui » pour une
    // date de fin, « il y a 15 ans » pour une naissance) plutot que sur une
    // date arbitraire au milieu de nulle part.
    return maximumDate ?? new Date();
  }

  const ouvrir = () => {
    setDraft(value ? fromIsoDate(value) : defaultDate());
    setOpen(true);
  };

  const onAndroidChange = (event: DateTimePickerEvent, date?: Date) => {
    setOpen(false);
    if (event.type === 'set' && date) onChange(toIsoDate(date));
  };

  return (
    <View style={styles.wrapper}>
      <Text variant="label" tone="muted">
        {label}
      </Text>
      <Pressable
        onPress={ouvrir}
        accessibilityRole="button"
        accessibilityLabel={label}
        accessibilityValue={{ text: value ? formatDateFr(value) : placeholder }}
        style={[
          styles.input,
          {
            backgroundColor: colors.surface,
            borderColor: error ? colors.danger : colors.border,
          },
        ]}
      >
        <Text variant="body" tone={value ? 'default' : 'muted'}>
          {value ? formatDateFr(value) : placeholder}
        </Text>
      </Pressable>
      {clearable && value ? (
        <Pressable
          onPress={() => onChange(null)}
          accessibilityRole="button"
          hitSlop={spacing.sm}
        >
          <Text variant="small" tone="accent">
            {clearLabel}
          </Text>
        </Pressable>
      ) : null}
      {error ? (
        <Text variant="small" tone="danger" accessibilityLiveRegion="polite" role="alert">
          {error}
        </Text>
      ) : null}

      {open && Platform.OS === 'android' ? (
        <DateTimePicker
          value={draft}
          mode="date"
          display="default"
          minimumDate={minimumDate}
          maximumDate={maximumDate}
          onChange={onAndroidChange}
        />
      ) : null}

      {Platform.OS === 'ios' ? (
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
          <View style={[styles.sheet, { backgroundColor: colors.surface }]}>
            <Text variant="sectionTitle">{label}</Text>
            <DateTimePicker
              value={draft}
              mode="date"
              display="spinner"
              locale="fr-FR"
              minimumDate={minimumDate}
              maximumDate={maximumDate}
              themeVariant={scheme}
              onChange={(_event, date) => date && setDraft(date)}
            />
            <Button
              label="Valider"
              onPress={() => {
                onChange(toIsoDate(draft));
                setOpen(false);
              }}
            />
          </View>
        </Modal>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: { gap: spacing.xs },
  input: {
    minHeight: 50,
    paddingHorizontal: spacing.lg,
    justifyContent: 'center',
    borderWidth: 1,
    borderRadius: radii.md,
  },
  backdrop: { flex: 1, backgroundColor: 'rgba(0, 0, 0, 0.4)' },
  sheet: {
    padding: spacing.xl,
    paddingBottom: spacing.xxl,
    borderTopLeftRadius: radii.lg,
    borderTopRightRadius: radii.lg,
    gap: spacing.md,
  },
});
