// Palette Jeuncy — source unique de verite cote mobile.
//
// Les valeurs viennent de CLAUDE.md section 2 (identite visuelle) et doivent y
// rester identiques : le web les porte dans tailwind.config.ts, il n'existe pas
// de generation commune entre les deux, la synchronisation est donc manuelle.
//
// Regle de CONVENTIONS.md section 5 transposee au natif : jamais de couleur
// hexadecimale en dur dans un composant, tout passe par ce fichier.

export const palette = {
  navy: '#061D4F',
  coral: '#FF2D55',
  orange: '#FF8A32',
  white: '#FFFFFF',
  offwhite: '#FAFAF8',
} as const;

// Degrade signature, reserve aux accents et aux CTA (CLAUDE.md section 2).
// En natif il se pose via expo-linear-gradient, qui attend un tableau de
// couleurs plutot qu'une chaine CSS.
export const signatureGradient = [palette.coral, palette.orange] as const;

export interface ThemeColors {
  /** Fond de l'ecran. */
  background: string;
  /** Fond des cartes et des champs, pose sur `background`. */
  surface: string;
  /** Fond legerement contraste : entetes, zones inactives. */
  surfaceMuted: string;
  /** Texte principal. */
  text: string;
  /** Texte secondaire, legendes. Contraste AA garanti sur `background`. */
  textMuted: string;
  /** Texte pose sur une surface coloree (bouton corail, badge). */
  textOnAccent: string;
  border: string;
  /** Accent principal — CTA, elements actifs. */
  accent: string;
  /** Accent secondaire — opportunites, badges. */
  accentWarm: string;
  danger: string;
  success: string;
}

// Mode clair : fond off-white, texte bleu nuit.
export const lightColors: ThemeColors = {
  background: palette.offwhite,
  surface: palette.white,
  surfaceMuted: '#F1F1EE',
  text: palette.navy,
  // Bleu nuit eclairci plutot qu'un gris neutre : reste dans la famille de la
  // charte tout en passant AA sur l'off-white (ratio ~7:1).
  textMuted: '#4A5875',
  textOnAccent: palette.white,
  border: '#E2E1DC',
  accent: palette.coral,
  accentWarm: palette.orange,
  danger: '#C81E3C',
  success: '#0E7C5A',
};

// Mode sombre : fond bleu nuit, texte off-white. Corail et orange restent
// constants d'un mode a l'autre — c'est ce que demande CLAUDE.md section 2.
export const darkColors: ThemeColors = {
  background: palette.navy,
  // Navy eclairci, pas un gris : une carte doit se detacher du fond sans
  // sortir de la charte.
  surface: '#0E2A66',
  surfaceMuted: '#0A2358',
  text: palette.offwhite,
  textMuted: '#A9B6D4',
  textOnAccent: palette.white,
  border: '#1B3A78',
  accent: palette.coral,
  accentWarm: palette.orange,
  // Rouge et vert eclaircis pour rester lisibles sur fond sombre.
  danger: '#FF6B85',
  success: '#3BD1A0',
};
