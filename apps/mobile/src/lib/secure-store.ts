import * as SecureStore from 'expo-secure-store';

// Coffre du telephone : Keychain sur iOS, Keystore sur Android.
//
// C'est ici que vit le refresh token, et nulle part ailleurs. Il ouvre une
// session de 7 jours : le ranger dans AsyncStorage reviendrait a l'ecrire en
// clair dans un fichier de l'application, lisible sur un appareil debride ou
// dans une sauvegarde non chiffree. Le cookie httpOnly joue ce role sur le
// web ; le coffre le joue en natif (voir MOBILE.md section 5.1).

const REFRESH_TOKEN_KEY = 'jeuncy.refresh-token';

// L'access token, lui, ne vient jamais ici : il dure 15 minutes et vit en
// memoire uniquement, exactement comme dans le store Zustand du web.

export async function readRefreshToken(): Promise<string | null> {
  try {
    return await SecureStore.getItemAsync(REFRESH_TOKEN_KEY);
  } catch {
    // Coffre indisponible (appareil verrouille au moment de la lecture, ou
    // simulateur mal configure) : on se comporte comme si aucune session
    // n'existait plutot que de faire planter le demarrage de l'application.
    return null;
  }
}

export async function writeRefreshToken(token: string): Promise<void> {
  try {
    await SecureStore.setItemAsync(REFRESH_TOKEN_KEY, token, {
      // Le jeton reste lisible apres un simple redemarrage, mais uniquement
      // sur cet appareil : il ne part pas dans les sauvegardes iCloud, ou il
      // pourrait etre restaure sur un autre telephone.
      keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
    });
  } catch {
    // Echec d'ecriture : la session vaut pour le lancement en cours, et
    // l'utilisateur devra se reconnecter au suivant. Preferable a un plantage.
  }
}

export async function clearRefreshToken(): Promise<void> {
  try {
    await SecureStore.deleteItemAsync(REFRESH_TOKEN_KEY);
  } catch {
    // Rien a faire de plus : la deconnexion cote serveur, elle, a deja eu lieu.
  }
}
