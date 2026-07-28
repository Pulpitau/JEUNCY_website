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
  const secondaryBadge =
    offer.cfa_organization_id !== null ? offer.diploma_level : offer.experience_level;

  return (
    <Link to={`/offres/${offer.id}`}>
      <Card className="h-full transition-all duration-200 hover:-translate-y-1 hover:border-primary hover:shadow-lg">
        <CardHeader>
          <div className="flex flex-wrap gap-1">
            <Badge variant="outline" className="w-fit">
              {CONTRACT_TYPE_LABELS[offer.contract_type]}
            </Badge>
            {secondaryBadge && (
              <Badge variant="outline" className="w-fit">
                {secondaryBadge}
              </Badge>
            )}
          </div>
          <CardTitle>{offer.title}</CardTitle>
          <CardDescription>
            {publisher?.name}
            {offer.city ? ` · ${offer.city}` : ''}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {offer.compensation && (
            <p className="mb-1 font-inter text-sm font-medium text-foreground">
              {offer.compensation}
            </p>
          )}
          <p className="line-clamp-3 font-inter text-sm text-muted-foreground">
            {offer.description}
          </p>
        </CardContent>
      </Card>
    </Link>
  );
}
