import { Ionicons } from '@expo/vector-icons';
import { useState } from 'react';
import { Modal, Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Button } from '@/components/ui/button';
import { Text } from '@/components/ui/text';
import type { DeckCard } from '@/hooks/use-discover-deck';
import { publisherOf } from '@/lib/api/job-offers';
import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

// Feuille qui suit un glissement a droite. Decision du 2026-09-22 : jamais
// de candidature envoyee sans geste explicite. Le glissement dit « ca
// m'interesse », la feuille demande ce qu'on en fait :
//
// - offre Jeuncy : envoyer le dossier maintenant (formulaire de candidature
//   existant, pre-rempli) ou juste marquer l'interet ;
// - offre partenaire : garder l'offre (liste « Gardees » de l'onglet
//   Candidatures) ou ouvrir tout de suite le site de l'employeur. Le mot
//   « match » n'apparait pas : il n'y en a pas de ce cote.
//
// La feuille ne parle pas au serveur : les appels vivent dans l'ecran, qui
// tient la pile et sait annoncer un match. Elle appelle des fonctions et
// attend qu'elles rendent la main.

export interface InterestSheetProps {
  /** Carte concernee ; null = feuille fermee. */
  card: DeckCard | null;
  /** Un appel est en cours : les boutons ne doivent pas partir deux fois. */
  busy: boolean;
  /** Offre Jeuncy : enregistre l'interet puis ouvre le formulaire. */
  onSendDossier: () => void;
  /** Offre Jeuncy : enregistre l'interet et s'arrete la. */
  onMarkInterest: () => void;
  /**
   * Offre partenaire : enregistre « Je garde », puis ouvre `openUrl` si
   * fourni. L'ecran ne ferme la feuille qu'au retour du navigateur.
   */
  onKeep: (openUrl?: string) => Promise<void>;
  /** « Ca m'interesse » restants aujourd'hui ; null = pas de quota actif. */
  likesRestants?: number | null;
  /** Le candidat se ravise : la carte revient dans la pile. */
  onCancel: () => void;
}

export function InterestSheet({
  card,
  busy,
  onSendDossier,
  onMarkInterest,
  onKeep,
  onCancel,
  likesRestants = null,
}: InterestSheetProps) {
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();

  // Derniere carte recue : la feuille met ~300 ms a glisser vers le bas
  // apres que `card` repasse a null, et pendant ce temps elle doit encore
  // afficher l'offre, pas un cadre vide. Etat derive pendant le rendu, comme
  // React le recommande pour « se souvenir de la valeur precedente ».
  const [shown, setShown] = useState(card);
  if (card !== null && card !== shown) setShown(card);

  // Bouton presse en dernier : seul lui porte l'indicateur d'attente, les
  // autres sont simplement desactives.
  const [presse, setPresse] = useState<'dossier' | 'interet' | 'garder' | 'site' | null>(
    null,
  );
  const appuyer = (bouton: NonNullable<typeof presse>, action: () => unknown) => () => {
    setPresse(bouton);
    void action();
  };

  // Pendant un appel, la feuille ne se ferme pas : la fermer remettrait la
  // carte dans la pile alors que le geste est peut-etre deja enregistre.
  const fermer = () => {
    if (!busy) onCancel();
  };

  // Le navigateur integre se presente PAR-DESSUS la feuille, qui ne se ferme
  // qu'a son retour : expo-web-browser presente Safari depuis le controleur
  // le plus haut, et si c'etait la feuille en train de disparaitre, iOS
  // l'emporterait avec elle. L'enregistrement passe AVANT l'ouverture : si
  // le candidat postule sur le site et ne revient pas, l'offre est quand meme
  // dans ses « Gardees ».

  return (
    <Modal
      visible={card !== null}
      transparent
      animationType="slide"
      onRequestClose={fermer}
      statusBarTranslucent
    >
      {/* Le voile ferme la feuille : meme geste que « Annuler ». */}
      <Pressable
        style={styles.backdrop}
        onPress={fermer}
        accessibilityRole="button"
        accessibilityLabel="Annuler et remettre l'offre dans la pile"
      />
      <View
        style={[
          styles.sheet,
          {
            backgroundColor: colors.surface,
            borderColor: colors.border,
            paddingBottom: insets.bottom + spacing.lg,
          },
        ]}
        accessibilityViewIsModal
      >
        <View style={[styles.grip, { backgroundColor: colors.border }]} />

        {shown ? (
          <>
            <View style={styles.heading}>
              <Ionicons
                name={shown.kind === 'jeuncy' ? 'heart' : 'bookmark'}
                size={22}
                color={shown.kind === 'jeuncy' ? colors.accent : colors.accentWarm}
              />
              <View style={styles.headingText}>
                <Text variant="sectionTitle" numberOfLines={2}>
                  {shown.offer.title}
                </Text>
                <Text variant="small" tone="muted" numberOfLines={1}>
                  {shown.kind === 'jeuncy'
                    ? (publisherOf(shown.offer)?.name ?? '')
                    : (shown.offer.company_name ?? 'Employeur non communiqué')}
                </Text>
              </View>
            </View>

            {shown.kind === 'jeuncy' ? (
              <View style={styles.actions}>
                <Button
                  label="Envoyer mon dossier maintenant"
                  disabled={busy}
                  loading={busy && presse === 'dossier'}
                  onPress={appuyer('dossier', onSendDossier)}
                />
                <Button
                  label="Juste marquer mon intérêt"
                  variant="secondary"
                  disabled={busy}
                  loading={busy && presse === 'interet'}
                  onPress={appuyer('interet', onMarkInterest)}
                />
                <Text variant="small" tone="muted" style={styles.hint}>
                  Ton dossier, c&apos;est ta candidature : téléphone, CV et un mot pour
                  l&apos;entreprise, pré-remplis. Marquer ton intérêt ne l&apos;envoie pas
                  : si l&apos;entreprise s&apos;intéresse aussi à toi, c&apos;est un
                  match, et tu enverras ton dossier à ce moment-là.
                </Text>
                {likesRestants !== null ? (
                  <Text variant="small" tone="muted" style={styles.hint}>
                    {likesRestants === 0
                      ? "Tu as utilisé tous tes « ça m'intéresse » du jour."
                      : `Il te reste ${likesRestants} « ça m'intéresse » aujourd'hui.`}
                  </Text>
                ) : null}
              </View>
            ) : (
              <View style={styles.actions}>
                <Button
                  label="Je garde"
                  disabled={busy}
                  loading={busy && presse === 'garder'}
                  onPress={appuyer('garder', () => onKeep())}
                />
                <Button
                  label="Ouvrir le site de l'employeur"
                  variant="secondary"
                  disabled={busy}
                  loading={busy && presse === 'site'}
                  onPress={appuyer('site', () => onKeep(shown.offer.apply_url))}
                />
                <Text variant="small" tone="muted" style={styles.hint}>
                  Offre partenaire : la candidature se fait sur le site de
                  l&apos;employeur, pas sur Jeuncy. Pense à joindre ton CV Jeuncy.
                </Text>
              </View>
            )}

            <Button label="Annuler" variant="ghost" disabled={busy} onPress={fermer} />
          </>
        ) : null}
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(6, 29, 79, 0.45)',
  },
  sheet: {
    paddingHorizontal: spacing.xl,
    paddingTop: spacing.md,
    gap: spacing.lg,
    borderTopLeftRadius: radii.lg,
    borderTopRightRadius: radii.lg,
    borderTopWidth: 1,
  },
  grip: {
    alignSelf: 'center',
    width: 40,
    height: 4,
    borderRadius: radii.pill,
  },
  heading: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.md },
  headingText: { flex: 1, gap: 2 },
  actions: { gap: spacing.md },
  hint: { textAlign: 'center' },
});
