import { useState } from 'react';
import { JobOfferStatus, ContractType } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { JobOfferForm } from '@/components/features/job-offers/JobOfferForm';
import { ApplicationsForOfferSection } from '@/components/features/job-offers/ApplicationsForOfferSection';
import type { JobOffer, JobOfferInput } from '@/lib/api/job-offers';

const STATUS_LABELS: Record<string, string> = {
  [JobOfferStatus.DRAFT]: 'Brouillon',
  [JobOfferStatus.PUBLISHED]: 'Publiée',
  [JobOfferStatus.EXPIRED]: 'Expirée',
  [JobOfferStatus.ARCHIVED]: 'Archivée',
};

const CONTRACT_TYPE_LABELS: Record<string, string> = {
  [ContractType.ALTERNANCE]: 'Alternance',
  [ContractType.SAISONNIER]: 'Saisonnier',
  [ContractType.BENEVOLAT]: 'Bénévolat',
};

interface JobOfferListItemProps {
  offer: JobOffer;
  onUpdate: (id: number, values: Partial<JobOfferInput>) => Promise<unknown>;
  onArchive: (id: number) => Promise<unknown>;
  onPublish: (id: number) => Promise<unknown>;
  isSubmitting: boolean;
  isPublishing: boolean;
}

export function JobOfferListItem({
  offer,
  onUpdate,
  onArchive,
  onPublish,
  isSubmitting,
  isPublishing,
}: JobOfferListItemProps) {
  const [isEditing, setIsEditing] = useState(false);
  const [showApplications, setShowApplications] = useState(false);

  if (isEditing) {
    return (
      <JobOfferForm
        offer={offer}
        isSubmitting={isSubmitting}
        onCancel={() => setIsEditing(false)}
        onSubmit={async (values) => {
          await onUpdate(offer.id, values);
          setIsEditing(false);
        }}
      />
    );
  }

  return (
    <div className="flex flex-col gap-3 rounded-md border border-border p-4">
      <div className="flex items-start justify-between gap-4">
        <div>
          <p className="font-poppins font-medium">{offer.title}</p>
          <p className="text-sm text-muted-foreground">
            {CONTRACT_TYPE_LABELS[offer.contract_type]}
            {offer.city ? ` · ${offer.city}` : ''}
          </p>
        </div>
        <Badge
          variant={
            offer.status === JobOfferStatus.PUBLISHED
              ? 'default'
              : offer.status === JobOfferStatus.ARCHIVED
                ? 'destructive'
                : 'secondary'
          }
        >
          {STATUS_LABELS[offer.status]}
        </Badge>
      </div>

      <p className="line-clamp-2 font-inter text-sm text-muted-foreground">
        {offer.description}
      </p>

      <div className="flex flex-wrap gap-2">
        {offer.status === JobOfferStatus.DRAFT && (
          <>
            <Button
              type="button"
              variant="gradient"
              size="sm"
              onClick={() => void onPublish(offer.id)}
              disabled={isPublishing}
            >
              {isPublishing ? 'Redirection…' : 'Publier (paiement)'}
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => setIsEditing(true)}
            >
              Modifier
            </Button>
          </>
        )}
        {offer.status !== JobOfferStatus.ARCHIVED && (
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => void onArchive(offer.id)}
            disabled={isSubmitting}
          >
            Archiver
          </Button>
        )}
        {offer.status === JobOfferStatus.PUBLISHED && (
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => setShowApplications((current) => !current)}
          >
            {showApplications ? 'Masquer les candidatures' : 'Voir les candidatures'}
          </Button>
        )}
      </div>

      {showApplications && <ApplicationsForOfferSection jobOfferId={offer.id} />}
    </div>
  );
}
