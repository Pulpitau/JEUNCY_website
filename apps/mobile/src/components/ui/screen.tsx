import {
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  View,
  type ViewStyle,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useTheme } from '@/theme/theme-provider';
import { spacing } from '@/theme/typography';

export interface ScreenProps {
  children: React.ReactNode;
  /** Rend le contenu defilant. A desactiver pour un ecran qui gere son propre defilement. */
  scroll?: boolean;
  /**
   * L'ecran est pousse dans une pile avec un en-tete natif : celui-ci occupe
   * deja la zone de l'encoche, il ne faut pas la reserver une seconde fois.
   */
  hasHeader?: boolean;
  contentStyle?: ViewStyle;
}

// Enveloppe commune a tous les ecrans : fond du theme, encoche et barre
// d'accueil respectees, et surtout remontee du contenu quand le clavier
// s'ouvre — sans quoi le champ en cours de saisie passe sous le clavier sur
// les petits iPhone.
export function Screen({
  children,
  scroll = true,
  hasHeader = false,
  contentStyle,
}: ScreenProps) {
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();

  const padding: ViewStyle = {
    paddingTop: (hasHeader ? 0 : insets.top) + spacing.lg,
    paddingBottom: insets.bottom + spacing.xl,
    paddingHorizontal: spacing.xl,
  };

  return (
    <KeyboardAvoidingView
      style={[styles.flex, { backgroundColor: colors.background }]}
      // 'padding' sur iOS, 'height' sur Android : les deux systemes ne
      // signalent pas le clavier de la meme facon.
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      {scroll ? (
        <ScrollView
          contentContainerStyle={[padding, contentStyle]}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode="on-drag"
        >
          {children}
        </ScrollView>
      ) : (
        <View style={[styles.flex, padding, contentStyle]}>{children}</View>
      )}
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
});
