import { useState } from 'react';
import {
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  StyleSheet,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Text } from '@/components/ui/text';
import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

// Petite feuille « Ou ? » : le departement des offres partenaires. Le
// perimetre de lancement est les Pyrenees-Orientales (66) ; l'ouverture
// par departement se fera par configuration cote serveur, ce reglage local
// sert au prototype et aux essais.
//
// Numeros acceptes : 01 a 95, 2A et 2B (Corse), 971 a 976 (outre-mer).

const DEPARTMENT_PATTERN = /^(0[1-9]|[1-8][0-9]|9[0-5]|2A|2B|97[1-6])$/;

export function isValidDepartment(value: string): boolean {
  return DEPARTMENT_PATTERN.test(value);
}

export interface DepartmentSheetProps {
  current: string;
  onSubmit: (department: string) => void;
  onClose: () => void;
}

export function DepartmentSheet({ current, onSubmit, onClose }: DepartmentSheetProps) {
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();
  const [value, setValue] = useState(current);
  const [touched, setTouched] = useState(false);

  const normalized = value.trim().toUpperCase();
  const valid = isValidDepartment(normalized);
  const error =
    touched && !valid ? 'Un numéro de département : 66, 31, 2A, 974…' : undefined;

  const submit = () => {
    setTouched(true);
    if (valid) onSubmit(normalized);
  };

  // Le parent ne monte cette feuille que le temps de l'ouverture : le champ
  // repart donc de la valeur en vigueur a chaque fois, sans effet de
  // synchronisation.
  return (
    <Modal
      visible
      transparent
      animationType="fade"
      onRequestClose={onClose}
      statusBarTranslucent
    >
      {/* La feuille est en bas de l'ecran et le champ prend le focus tout de
          suite : sans cette enveloppe, le clavier la recouvre entierement sur
          iPhone (un Modal ne se decale pas seul). Memes reglages que Screen. */}
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      >
        <Pressable
          style={styles.backdrop}
          onPress={onClose}
          accessibilityRole="button"
          accessibilityLabel="Fermer"
        />
        <View
          style={[
            styles.sheet,
            {
              backgroundColor: colors.surface,
              borderColor: colors.border,
              paddingBottom: insets.bottom + spacing.lg,
            },
          ]}
          accessibilityViewIsModal
        >
          <Text variant="title">Où ?</Text>
          <Text variant="small" tone="muted">
            Les offres partenaires de ce département viendront après les offres Jeuncy.
          </Text>
          <Field
            label="Département"
            value={value}
            onChangeText={(text) =>
              setValue(text.replace(/[^0-9a-zA-Z]/g, '').slice(0, 3))
            }
            onBlur={() => setTouched(true)}
            onSubmitEditing={submit}
            error={error}
            placeholder="66"
            autoCapitalize="characters"
            autoCorrect={false}
            autoFocus
            maxLength={3}
            returnKeyType="done"
          />
          <Button label="Valider" onPress={submit} />
          <Button label="Annuler" variant="ghost" onPress={onClose} />
        </View>
      </KeyboardAvoidingView>
    </Modal>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(6, 29, 79, 0.45)',
  },
  sheet: {
    paddingHorizontal: spacing.xl,
    paddingTop: spacing.xl,
    gap: spacing.md,
    borderTopLeftRadius: radii.lg,
    borderTopRightRadius: radii.lg,
    borderTopWidth: 1,
  },
});
