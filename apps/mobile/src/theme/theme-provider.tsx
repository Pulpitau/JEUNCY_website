import AsyncStorage from '@react-native-async-storage/async-storage';
import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import { useColorScheme } from 'react-native';

import { darkColors, lightColors, type ThemeColors } from './colors';

// Trois etats, et non deux : sur mobile, la convention est de suivre le
// reglage du telephone (qui bascule seul le soir), avec la possibilite de
// forcer un mode. Le web, lui, garde son simple bouton clair/sombre.
export type ThemePreference = 'system' | 'light' | 'dark';

const STORAGE_KEY = 'jeuncy.theme-preference';

interface ThemeContextValue {
  colors: ThemeColors;
  /** Le mode reellement applique, une fois la preference resolue. */
  scheme: 'light' | 'dark';
  preference: ThemePreference;
  setPreference: (preference: ThemePreference) => void;
}

const ThemeContext = createContext<ThemeContextValue | null>(null);

export function ThemeProvider({ children }: { children: ReactNode }): ReactNode {
  const systemScheme = useColorScheme();
  const [preference, setPreferenceState] = useState<ThemePreference>('system');

  // AsyncStorage et non SecureStore : une preference d'affichage n'est pas un
  // secret, et le coffre du telephone est un stockage lent, reserve aux jetons.
  useEffect(() => {
    let annule = false;
    AsyncStorage.getItem(STORAGE_KEY)
      .then((valeur) => {
        if (!annule && (valeur === 'light' || valeur === 'dark' || valeur === 'system')) {
          setPreferenceState(valeur);
        }
      })
      .catch(() => {
        // Preference illisible : on reste sur 'system', qui est le defaut.
      });

    return () => {
      annule = true;
    };
  }, []);

  const value = useMemo<ThemeContextValue>(() => {
    // useColorScheme peut rendre null ou 'unspecified' (telephone sans
    // preference declaree) : tout ce qui n'est pas explicitement sombre est
    // traite comme clair, plutot que de laisser passer une troisieme valeur.
    const scheme: 'light' | 'dark' =
      preference === 'system' ? (systemScheme === 'dark' ? 'dark' : 'light') : preference;

    return {
      scheme,
      colors: scheme === 'dark' ? darkColors : lightColors,
      preference,
      setPreference: (suivante) => {
        setPreferenceState(suivante);
        void AsyncStorage.setItem(STORAGE_KEY, suivante).catch(() => {
          // Echec d'ecriture : le choix vaut pour la session en cours, il
          // sera simplement oublie au prochain lancement.
        });
      },
    };
  }, [preference, systemScheme]);

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useTheme(): ThemeContextValue {
  const contexte = useContext(ThemeContext);
  if (!contexte) {
    throw new Error("useTheme doit etre utilise a l'interieur de <ThemeProvider>.");
  }

  return contexte;
}
