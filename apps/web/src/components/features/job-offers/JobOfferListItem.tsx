import { useState } from 'react';
import { JobOfferStatus, ContractType, PaymentStatus } from '@jeuncy/shared';
import { Lock } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { JobOfferForm } from '@/components/features/job-offers/JobOfferForm';
import { ApplicationsForOfferSection } from '@/components/features/job-offers/ApplicationsForOfferSection';
import { ApiError } from '@/lib/api/client';
import { WORK_MODE_LABELS } from '@/lib/work-mode-labels';
import { offerPriceLabel, type JobOffer, type JobOfferInput } from '@/lib/api/job-offers';
import { formatCompensation } from '@/lib/format-compensation';

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
  [ContractType.JOB_ETUDIANT]: 'Job étudiant',
  [ContractType.STAGE]: 'Stage',
};

interface JobOfferListItemProps {
  offer: JobOffer;
  onUpdate: (id: number, values: Partial<JobOfferInput>) => Promise<unknown>;
  onArchive: (id: number) => Promise<unknown>;
  onDelete: (id: number) => Promise<unknown>;
  isDeleting: boolean;
  onPublish: (id: number) => Promise<unknown>;
  isSubmitting: boolean;
  isPublishing: boolean;
  canUseTrial: boolean;
  trialAvailable: boolean;
  trialOffersRemaining: number;
  onPublishTrial: (id: number) => Promise<unknown>;
  isPublishingTrial: boolean;
  hasActiveSubscription: boolean;
  onPublishViaSubscription: (id: number) => Promise<unknown>;
  isPublishingViaSubscription: boolean;
}

export function JobOfferListItem({
  offer,
  onUpdate,
  onArchive,
  onDelete,
  isDeleting,
  onPublish,
  isSubmitting,
  isPublishing,
  canUseTrial,
  trialAvailable,
  trialOffersRemaining,
  onPublishTrial,
  isPublishingTrial,
  hasActiveSubscription,
  onPublishViaSubscription,
  isPublishingViaSubscription,
}: JobOfferListItemProps) {
  const [isEditing, setIsEditing] = useState(false);
  const [showApplications, setShowApplications] = useState(false);
  const [updateError, setUpdateError] = useState<string | null>(null);

  if (isEditing) {
    return (
      <JobOfferForm
        variant={offer.cfa_organization_id ? 'CFA' : 'COMPANY'}
        offer={offer}
        isSubmitting={isSubmitting}
        submitError={updateError}
        onCancel={() => setIsEditing(false)}
        onSubmit={async (values) => {
          setUpdateError(null);
          try {
            await onUpdate(offer.id, values);
            setIsEditing(false);
          } catch (error) {
            setUpdateError(
              error instanceof ApiError
                ? error.message
                : "Impossible de mettre à jour l'offre pour le moment.",
            );
          }
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
            {offer.work_mode ? ` · ${WORK_MODE_LABELS[offer.work_mode]}` : ''}
            {formatCompensation(
              offer.compensation_amount,
              offer.compensation_period,
              offer.compensation,
            )
              ? ` · ${formatCompensation(offer.compensation_amount, offer.compensation_period, offer.compensation)}`
              : ''}
          </p>
        </div>
        <div className="flex flex-col items-end gap-1">
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
          {offer.payment_status === PaymentStatus.TRIAL && (
            <Badge variant="outline">Essai gratuit</Badge>
          )}
          {offer.status === JobOfferStatus.PUBLISHED &&
            !offer.applications_unlocked_at &&
            !hasActiveSubscription && (
              <Badge variant="outline" className="gap-1">
                <Lock className="h-3 w-3" aria-hidden="true" />
                Candidatures verrouillées
              </Badge>
            )}
        </div>
      </div>

      <p className="line-clamp-2 font-inter text-sm text-muted-foreground">
        {offer.description}
      </p>

      {offer.skills.length > 0 && (
        <div className="flex flex-wrap gap-1">
          {offer.skills.map((skill) => (
            <Badge key={skill.id} variant="secondary">
              {skill.name}
            </Badge>
          ))}
        </div>
      )}

      <div className="flex flex-wrap gap-2">
        {offer.status === JobOfferStatus.DRAFT && (
          <>
            {hasActiveSubscription ? (
              <Button
                type="button"
                variant="gradient"
                size="sm"
                onClick={() => void onPublishViaSubscription(offer.id)}
                disabled={isPublishingViaSubscription}
              >
                {isPublishingViaSubscription
                  ? 'Publication…'
                  : 'Publier (inclus dans l’abonnement)'}
              </Button>
            ) : canUseTrial && trialAvailable ? (
              <Button
                type="button"
                variant="gradient"
                size="sm"
                onClick={() => void onPublishTrial(offer.id)}
                disabled={isPublishingTrial}
              >
                {isPublishingTrial
                  ? 'Publication…'
                  : `Publier gratuitement (essai — ${trialOffersRemaining} restante${trialOffersRemaining > 1 ? 's' : ''})`}
              </Button>
            ) : (
              <Button
                type="button"
                variant="gradient"
                size="sm"
                onClick={() => void onPublish(offer.id)}
                disabled={isPublishing}
              >
                {isPublishing
                  ? 'Redirection…'
                  : `Publier (paiement — ${offerPriceLabel(offer)})`}
              </Button>
            )}
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
        {offer.status === JobOfferStatus.ARCHIVED &&
          offer.payment_status === PaymentStatus.TRIAL &&
          (hasActiveSubscription ? (
            <Button
              type="button"
              variant="gradient"
              size="sm"
              onClick={() => void onPublishViaSubscription(offer.id)}
              disabled={isPublishingViaSubscription}
            >
              {isPublishingViaSubscription
                ? 'Publication…'
                : 'Republier (inclus dans l’abonnement)'}
            </Button>
          ) : (
            <Button
              type="button"
              variant="gradient"
              size="sm"
              onClick={() => void onPublish(offer.id)}
              disabled={isPublishing}
            >
              {isPublishing
                ? 'Redirection…'
                : `Payer pour republier (${offerPriceLabel(offer)})`}
            </Button>
          ))}
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
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="text-destructive hover:bg-destructive/10 hover:text-destructive"
          onClick={() => {
            if (
              window.confirm(
                `Supprimer définitivement l'offre « ${offer.title} » ? Cette action est irréversible.`,
              )
            ) {
              void onDelete(offer.id);
            }
          }}
          disabled={isDeleting}
        >
          {isDeleting ? 'Suppression…' : 'Supprimer'}
        </Button>
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
