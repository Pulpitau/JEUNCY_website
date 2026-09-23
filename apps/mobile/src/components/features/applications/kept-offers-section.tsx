import * as WebBrowser from 'expo-web-browser';
import { Alert, StyleSheet, View } from 'react-native';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Section, SectionEmpty } from '@/components/ui/section';
import { Text } from '@/components/ui/text';
import { useKeptOffers, useMarkKeptDone } from '@/hooks/use-kept-offers';
import type { ExternalInterest } from '@/lib/api/external-interests';
import { spacing } from '@/theme/typography';

// Offres partenaires « gardées » depuis la pile Découvrir.
//
// Elles viennent du serveur (`GET external-interests`) depuis le lot 3. Les
// lignes y sont DÉNORMALISÉES (titre, employeur, ville, lien) : l'import de
// nuit supprime les offres absentes de l'export, et une offre gardée doit
// survivre à la disparition de l'offre qui l'a créée. C'est pour ça que
// cette liste ne recharge pas les offres — elle lit ses propres copies.
//
// CE N'EST PAS UNE CANDIDATURE, et la section le dit. Jeuncy n'a aucun moyen
// de savoir si le candidat a postulé sur le site de l'employeur : « J'ai
// postulé » est une note qu'il se laisse à lui-même, pas un suivi. Laisser
// croire l'inverse serait la pire des promesses — celle d'un statut qui ne
// bougera jamais.

export function KeptOffersSection() {
  const { data, isPending } = useKeptOffers();
  const marquerFait = useMarkKeptDone();
  const kept = data ?? [];

  const confirmerFait = (interest: ExternalInterest) => {
    Alert.alert(
      'Tu as postulé sur le site ?',
      'On marquera cette offre comme faite. Jeuncy ne peut pas le vérifier : c’est une note pour toi.',
      [
        { text: 'Pas encore', style: 'cancel' },
        { text: "C'est fait", onPress: () => marquerFait.mutate(interest.id) },
      ],
    );
  };

  return (
    <Section title="Gardées (site partenaire)">
      {isPending ? (
        <SectionEmpty>Chargement…</SectionEmpty>
      ) : kept.length === 0 ? (
        <SectionEmpty>
          Les offres partenaires que tu gardes depuis Découvrir apparaissent ici. La
          candidature se fait sur le site de l&apos;employeur.
        </SectionEmpty>
      ) : (
        kept.map((interest) => (
          <Card key={interest.id}>
            <View style={styles.badges}>
              <Badge label="Offre partenaire" tone="warm" />
              {interest.done_at ? <Badge label="Postulé" tone="success" /> : null}
            </View>
            <Text variant="sectionTitle">{interest.title ?? 'Offre partenaire'}</Text>
            <Text variant="small" tone="muted">
              {interest.company_name ?? 'Employeur non communiqué'}
              {interest.city ? ` · ${interest.city}` : ''}
            </Text>
            <Button
              label="Ouvrir le site de l'employeur"
              variant="secondary"
              onPress={() => void WebBrowser.openBrowserAsync(interest.apply_url)}
            />
            {interest.done_at ? null : (
              <Button
                label="J'ai postulé"
                variant="ghost"
                onPress={() => confirmerFait(interest)}
              />
            )}
          </Card>
        ))
      )}
    </Section>
  );
}

const styles = StyleSheet.create({
  badges: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
});
