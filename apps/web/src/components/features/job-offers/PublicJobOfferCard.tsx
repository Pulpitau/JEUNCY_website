import { Link } from 'react-router-dom';
import { Building2 } from 'lucide-react';
import { ContractType } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from '@/components/ui/card';
import { WORK_MODE_LABELS } from '@/lib/work-mode-labels';
import type { PublicJobOffer } from '@/lib/api/job-offers';
import { formatCompensation } from '@/lib/format-compensation';

const CONTRACT_TYPE_LABELS: Record<string, string> = {
  [ContractType.ALTERNANCE]: 'Alternance',
  [ContractType.SAISONNIER]: 'Saisonnier',
  [ContractType.BENEVOLAT]: 'Bénévolat',
  [ContractType.JOB_ETUDIANT]: 'Job étudiant',
  [ContractType.STAGE]: 'Stage',
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
            {offer.work_mode && (
              <Badge variant="outline" className="w-fit">
                {WORK_MODE_LABELS[offer.work_mode]}
              </Badge>
            )}
          </div>
          <CardTitle>{offer.title}</CardTitle>
          <div className="flex items-center gap-2">
            {publisher?.logo_url ? (
              <img
                src={publisher.logo_url}
                alt=""
                className="h-6 w-6 shrink-0 rounded border border-border object-contain"
              />
            ) : (
              <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded border border-border bg-muted">
                <Building2
                  className="h-3.5 w-3.5 text-muted-foreground"
                  aria-hidden="true"
                />
              </div>
            )}
            <CardDescription>
              {publisher?.name}
              {offer.city ? ` · ${offer.city}` : ''}
            </CardDescription>
          </div>
        </CardHeader>
        <CardContent>
          {formatCompensation(
            offer.compensation_amount,
            offer.compensation_period,
            offer.compensation,
          ) && (
            <p className="mb-1 font-inter text-sm font-medium text-foreground">
              {formatCompensation(
                offer.compensation_amount,
                offer.compensation_period,
                offer.compensation,
              )}
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
