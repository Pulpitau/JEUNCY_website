// @ts-check
// Config racine pour les fichiers hors apps/* et packages/* (ex: prisma/seed.ts).
// Chaque app a sa propre config plus complete (apps/web, apps/api, packages/shared).
import js from '@eslint/js';
import eslintPluginPrettierRecommended from 'eslint-plugin-prettier/recommended';
import tseslint from 'typescript-eslint';

export default tseslint.config(
  { ignores: ['apps/**', 'packages/**', 'eslint.config.mjs'] },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  {
    rules: {
      '@typescript-eslint/no-explicit-any': 'error',
    },
  },
  eslintPluginPrettierRecommended,
);
