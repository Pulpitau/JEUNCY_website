import { Link } from 'react-router-dom';
import { ContractType } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from '@/components/ui/card';
import type { PublicJobOffer } from '@/lib/api/job-offers';

const CONTRACT_TYPE_LABELS: Record<string, string> = {
  [ContractType.ALTERNANCE]: 'Alternance',
  [ContractType.SAISONNIER]: 'Saisonnier',
  [ContractType.BENEVOLAT]: 'Bénévolat',
};

export function PublicJobOfferCard({ offer }: { offer: PublicJobOffer }) {
  const publisher = offer.company ?? offer.cfa_organization;

  return (
    <Link to={`/offres/${offer.id}`}>
      <Card className="h-full transition-colors hover:border-primary">
        <CardHeader>
          <Badge variant="outline" className="w-fit">
            {CONTRACT_TYPE_LABELS[offer.contract_type]}
          </Badge>
          <CardTitle>{offer.title}</CardTitle>
          <CardDescription>
            {publisher?.name}
            {offer.city ? ` · ${offer.city}` : ''}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <p className="line-clamp-3 font-inter text-sm text-muted-foreground">
            {offer.description}
          </p>
        </CardContent>
      </Card>
    </Link>
  );
}
