import type { Config } from 'tailwindcss';
import animate from 'tailwindcss-animate';

// Thème custom Jeuncy (couleurs/typos) ajouté à l'étape 2 (design system)
export default {
  darkMode: ['selector', '[data-theme="dark"]'],
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {},
  },
  plugins: [animate],
} satisfies Config;
