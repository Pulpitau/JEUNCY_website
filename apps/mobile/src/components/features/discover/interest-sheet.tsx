import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useState } from 'react';
import { Modal, Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Button } from '@/components/ui/button';
import { Text } from '@/components/ui/text';
import type { DeckCard } from '@/hooks/use-discover-deck';
import { publisherOf } from '@/lib/api/job-offers';
import { useSwipeStore } from '@/store/swipe-store';
import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

// Feuille qui suit un glissement a droite. Decision du 2026-09-22 : jamais de
// candidature envoyee sans geste explicite. Le glissement dit « ca
// m'interesse », la feuille demande ce qu'on en fait :
//
// - offre Jeuncy : envoyer le dossier maintenant (formulaire de candidature
//   existant, pre-rempli) ou juste marquer l'interet ;
// - offre partenaire : garder l'offre (liste « Gardees » de l'onglet
//   Candidatures) ou ouvrir tout de suite le site de l'employeur.
//
// « Annuler » remet la carte dans la pile. Le prototype enregistre tout sur
// le telephone (voir swipe-store.ts) et le dit tel quel au candidat.

export interface InterestSheetProps {
  /** Carte concernee ; null = feuille fermee. */
  card: DeckCard | null;
  /** Le geste est acte (interet, dossier, garde) : la carte quitte la pile. */
  onDone: (message: string | null) => void;
  /** Le candidat se ravise : la carte revient. */
  onCancel: () => void;
}

export const INTEREST_SAVED_MESSAGE =
  "Intérêt enregistré sur ce téléphone. L'envoi à l'entreprise arrive avec la prochaine version.";

export const KEEP_SAVED_MESSAGE = 'Offre gardée. Retrouve-la dans Candidatures.';

export function InterestSheet({ card, onDone, onCancel }: InterestSheetProps) {
  const router = useRouter();
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();
  const record = useSwipeStore((state) => state.record);

  // Derniere carte recue : la feuille met ~300 ms a glisser vers le bas
  // apres `card` repasse a null, et pendant ce temps elle doit encore
  // afficher l'offre, pas un cadre vide. Etat derive pendant le rendu, comme
  // React le recommande pour « se souvenir de la valeur precedente ».
  const [shown, setShown] = useState(card);
  if (card !== null && card !== shown) setShown(card);

  const markInterest = (current: DeckCard) => {
    record({ key: current.key, decision: 'INTEREST' });
    onDone(INTEREST_SAVED_MESSAGE);
  };

  const sendDossier = (current: DeckCard & { kind: 'jeuncy' }) => {
    // L'interet est enregistre meme si le candidat referme le formulaire
    // sans envoyer : la carte ne revient pas, il a dit oui.
    record({ key: current.key, decision: 'INTEREST' });
    onDone(null);
    router.push({
      pathname: '/offres/[id]/postuler',
      params: { id: String(current.offer.id) },
    });
  };

  const keep = (current: DeckCard & { kind: 'lba' }) => {
    record({
      key: current.key,
      decision: 'KEEP',
      kept: {
        id: current.offer.id,
        title: current.offer.title,
        employer: current.offer.company_name,
        city: current.offer.city,
        applyUrl: current.offer.apply_url,
      },
    });
  };

  const keepOnly = (current: DeckCard & { kind: 'lba' }) => {
    keep(current);
    onDone(KEEP_SAVED_MESSAGE);
  };

  const keepAndOpen = (current: DeckCard & { kind: 'lba' }) => {
    keep(current);
    // Le navigateur integre se presente PAR-DESSUS la feuille, et la feuille
    // ne se ferme qu'a son retour (la promesse se resout a la fermeture du
    // navigateur sur iOS, tout de suite sur Android). Fermer la feuille
    // d'abord ne marche pas : expo-web-browser presente Safari depuis le
    // controleur le plus haut, qui serait alors cette feuille en train de
    // disparaitre — iOS l'emporte avec elle et le site ne s'ouvre jamais.
    void WebBrowser.openBrowserAsync(current.offer.apply_url).finally(() =>
      onDone(KEEP_SAVED_MESSAGE),
    );
  };

  return (
    <Modal
      visible={card !== null}
      transparent
      animationType="slide"
      onRequestClose={onCancel}
      statusBarTranslucent
    >
      {/* Le voile ferme la feuille : meme geste que « Annuler ». */}
      <Pressable
        style={styles.backdrop}
        onPress={onCancel}
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
              <Ionicons name="heart" size={22} color={colors.accent} />
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
                  onPress={() => sendDossier(shown)}
                />
                <Button
                  label="Juste marquer mon intérêt"
                  variant="secondary"
                  onPress={() => markInterest(shown)}
                />
                <Text variant="small" tone="muted" style={styles.hint}>
                  Ton dossier, c&apos;est ta candidature : téléphone, CV et un mot pour
                  l&apos;entreprise, pré-remplis. Marquer ton intérêt ne l&apos;envoie
                  pas.
                </Text>
              </View>
            ) : (
              <View style={styles.actions}>
                <Button label="Je garde" onPress={() => keepOnly(shown)} />
                <Button
                  label="Ouvrir le site de l'employeur"
                  variant="secondary"
                  onPress={() => keepAndOpen(shown)}
                />
                <Text variant="small" tone="muted" style={styles.hint}>
                  Offre partenaire : la candidature se fait sur le site de
                  l&apos;employeur, pas sur Jeuncy. Pense à joindre ton CV Jeuncy.
                </Text>
              </View>
            )}

            <Button label="Annuler" variant="ghost" onPress={onCancel} />
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
