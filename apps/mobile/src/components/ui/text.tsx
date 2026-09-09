import { Text as RNText, type TextProps as RNTextProps } from 'react-native';

import { useTheme } from '@/theme/theme-provider';
import { typeScale } from '@/theme/typography';

type Variant = keyof typeof typeScale;
type Tone = 'default' | 'muted' | 'accent' | 'onAccent' | 'danger';

export interface TextProps extends RNTextProps {
  variant?: Variant;
  tone?: Tone;
}

// Tout texte de l'application passe par ici : c'est ce qui garantit que Poppins
// et Inter sont reellement appliquees (une police custom mal nommee retombe
// silencieusement sur la police systeme, sans erreur) et qu'aucune couleur
// n'est ecrite en dur dans un ecran.
export function Text({ variant = 'body', tone = 'default', style, ...props }: TextProps) {
  const { colors } = useTheme();

  const color = {
    default: colors.text,
    muted: colors.textMuted,
    accent: colors.accent,
    onAccent: colors.textOnAccent,
    danger: colors.danger,
  }[tone];

  return <RNText {...props} style={[typeScale[variant], { color }, style]} />;
}
