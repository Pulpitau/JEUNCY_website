import { StyleSheet, View } from 'react-native';

import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Text } from '@/components/ui/text';
import { isCfaOffer, publisherOf, type PublicJobOffer } from '@/lib/api/job-offers';
import { formatCompensation } from '@/lib/format-compensation';
import { CONTRACT_TYPE_LABELS, WORK_MODE_LABELS } from '@/lib/labels';
import { spacing } from '@/theme/typography';

import { PublisherAvatar } from './publisher-avatar';

export interface JobOfferCardProps {
  offer: PublicJobOffer;
  onPress: () => void;
}

// Equivalent natif de PublicJobOfferCard (web) : memes informations, meme
// ordre, pour qu'un candidat qui passe du site a l'app retrouve ses reperes.
export function JobOfferCard({ offer, onPress }: JobOfferCardProps) {
  const publisher = publisherOf(offer);
  // Une offre de CFA met en avant le niveau vise, une offre d'entreprise
  // l'experience demandee — comme sur le web.
  const secondaryBadge = isCfaOffer(offer) ? offer.diploma_level : offer.experience_level;
  const remuneration = formatCompensation(
    offer.compensation_amount,
    offer.compensation_period,
    offer.compensation,
  );

  return (
    <Card
      onPress={onPress}
      accessibilityLabel={`${offer.title}, ${publisher?.name ?? ''}`}
    >
      <View style={styles.badges}>
        <Badge label={CONTRACT_TYPE_LABELS[offer.contract_type]} tone="accent" />
        {secondaryBadge ? <Badge label={secondaryBadge} /> : null}
        {offer.work_mode ? <Badge label={WORK_MODE_LABELS[offer.work_mode]} /> : null}
      </View>

      <Text variant="sectionTitle">{offer.title}</Text>

      <View style={styles.publisher}>
        <PublisherAvatar publisher={publisher} size={24} />
        <Text variant="small" tone="muted" numberOfLines={1} style={styles.publisherName}>
          {publisher?.name}
          {offer.city ? ` · ${offer.city}` : ''}
        </Text>
      </View>

      {remuneration ? <Text variant="bodyStrong">{remuneration}</Text> : null}

      <Text variant="small" tone="muted" numberOfLines={3}>
        {offer.description}
      </Text>
    </Card>
  );
}

const styles = StyleSheet.create({
  badges: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  publisher: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  publisherName: { flex: 1 },
});
