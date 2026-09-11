import { Ionicons } from '@expo/vector-icons';
import { useMutation } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';

import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Screen } from '@/components/ui/screen';
import { Text } from '@/components/ui/text';
import { useInvalidateProfile } from '@/hooks/use-candidate-profile';
import { ApiError } from '@/lib/api/client';
import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

export interface TagListEditorProps {
  initial: string[];
  onSave: (names: string[]) => Promise<unknown>;
  placeholder: string;
  hint: string;
  /** Limite du serveur (SyncSkillsRequest / SyncSoftwareRequest). */
  max?: number;
  maxLength?: number;
}

// Editeur d'une liste de noms (competences, logiciels) : on tape, on valide
// au clavier, la puce apparait ; un tap sur sa croix la retire. Rien ne part
// au serveur avant « Enregistrer », qui remplace la liste entiere (PUT) —
// c'est le contrat de l'API, un seul appel quel que soit le nombre de
// modifications.
export function TagListEditor({
  initial,
  onSave,
  placeholder,
  hint,
  max = 30,
  maxLength = 50,
}: TagListEditorProps) {
  const router = useRouter();
  const { colors } = useTheme();
  const invalidate = useInvalidateProfile();
  const [names, setNames] = useState<string[]>(initial);
  const [draft, setDraft] = useState('');
  const [erreur, setErreur] = useState<string | null>(null);

  const save = useMutation({
    mutationFn: onSave,
    onSuccess: async () => {
      await invalidate();
      router.back();
    },
    onError: (error) =>
      setErreur(error instanceof ApiError ? error.message : 'Une erreur est survenue.'),
  });

  const ajouter = () => {
    const nom = draft.trim();
    setErreur(null);
    if (!nom) return;
    if (nom.length > maxLength) {
      setErreur(`${maxLength} caractères maximum.`);

      return;
    }
    if (names.length >= max) {
      setErreur(`${max} maximum.`);

      return;
    }
    // Doublon insensible a la casse : « excel » et « Excel » sont le meme
    // logiciel, le serveur les dedoublonnerait de toute facon.
    if (names.some((n) => n.toLowerCase() === nom.toLowerCase())) {
      setDraft('');

      return;
    }
    setNames([...names, nom]);
    setDraft('');
  };

  const retirer = (nom: string) => setNames(names.filter((n) => n !== nom));

  return (
    <Screen hasHeader>
      <View style={styles.form}>
        <Text variant="small" tone="muted">
          {hint}
        </Text>
        <Field
          label="Ajouter"
          value={draft}
          onChangeText={setDraft}
          placeholder={placeholder}
          error={erreur ?? undefined}
          // Valider au clavier ajoute la puce et garde le clavier ouvert pour
          // enchainer : c'est le geste naturel pour saisir une liste.
          returnKeyType="done"
          blurOnSubmit={false}
          onSubmitEditing={ajouter}
          autoCapitalize="none"
          autoFocus
        />

        {names.length === 0 ? (
          <Text variant="small" tone="muted">
            Rien pour l&apos;instant.
          </Text>
        ) : (
          <View style={styles.chips} accessibilityRole="list">
            {names.map((nom) => (
              <View
                key={nom}
                style={[
                  styles.chip,
                  { backgroundColor: colors.surface, borderColor: colors.border },
                ]}
              >
                <Text variant="label">{nom}</Text>
                <Pressable
                  onPress={() => retirer(nom)}
                  accessibilityRole="button"
                  accessibilityLabel={`Retirer ${nom}`}
                  hitSlop={spacing.sm}
                >
                  <Ionicons name="close-circle" size={18} color={colors.textMuted} />
                </Pressable>
              </View>
            ))}
          </View>
        )}

        <Text variant="small" tone="muted">
          {names.length} / {max}
        </Text>

        <Button
          label="Enregistrer"
          onPress={() => save.mutate(names)}
          loading={save.isPending}
        />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  form: { gap: spacing.lg },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xs,
    paddingLeft: spacing.md,
    paddingRight: spacing.sm,
    paddingVertical: spacing.sm,
    borderRadius: radii.pill,
    borderWidth: 1,
  },
});
