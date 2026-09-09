// Typographie Jeuncy : Poppins pour les titres et les CTA, Inter pour les
// textes longs (CLAUDE.md section 2).
//
// En natif, une famille de police n'a pas de graisse : chaque graisse est un
// fichier distinct, charge sous son propre nom. Ecrire `fontWeight: '600'` sur
// une police custom ne fait rien sur Android — il faut nommer la bonne famille.
// D'ou ces constantes, a utiliser partout plutot que le couple
// famille + graisse.

export const fonts = {
  // Titres, boutons, chiffres mis en avant.
  displayRegular: 'Poppins_400Regular',
  displayMedium: 'Poppins_500Medium',
  displaySemiBold: 'Poppins_600SemiBold',
  displayBold: 'Poppins_700Bold',
  // Corps de texte, formulaires, listes.
  bodyRegular: 'Inter_400Regular',
  bodyMedium: 'Inter_500Medium',
  bodySemiBold: 'Inter_600SemiBold',
} as const;

// Echelle typographique. Les tailles suivent les usages mobiles courants ;
// lineHeight est toujours explicite, faute de quoi Android et iOS ne calculent
// pas le meme interligne pour une police custom.
export const typeScale = {
  hero: { fontFamily: fonts.displayBold, fontSize: 30, lineHeight: 38 },
  title: { fontFamily: fonts.displaySemiBold, fontSize: 22, lineHeight: 29 },
  sectionTitle: { fontFamily: fonts.displaySemiBold, fontSize: 17, lineHeight: 23 },
  body: { fontFamily: fonts.bodyRegular, fontSize: 15, lineHeight: 22 },
  bodyStrong: { fontFamily: fonts.bodySemiBold, fontSize: 15, lineHeight: 22 },
  small: { fontFamily: fonts.bodyRegular, fontSize: 13, lineHeight: 18 },
  label: { fontFamily: fonts.bodyMedium, fontSize: 13, lineHeight: 18 },
  button: { fontFamily: fonts.displaySemiBold, fontSize: 16, lineHeight: 21 },
} as const;

// Espacements et rayons, pour eviter les valeurs magiques dans les composants.
export const spacing = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 24,
  xxl: 32,
} as const;

export const radii = {
  sm: 8,
  md: 12,
  lg: 16,
  pill: 999,
} as const;
