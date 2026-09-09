// @ts-check
// Config ESLint de l'application mobile.
//
// Elle est indispensable et pas seulement confortable : lint-staged lance
// `eslint --fix` sur tout fichier .ts/.tsx modifie du depot (.lintstagedrc.json
// a la racine). Sans config ici, chaque commit touchant apps/mobile echouerait
// sur une erreur de configuration ESLint, pas sur une erreur de code.
import expoConfig from 'eslint-config-expo/flat.js';
import eslintPluginPrettierRecommended from 'eslint-plugin-prettier/recommended';

export default [
  ...expoConfig,
  eslintPluginPrettierRecommended,
  {
    ignores: ['dist/*', '.expo/*', 'expo-env.d.ts'],
  },
];
