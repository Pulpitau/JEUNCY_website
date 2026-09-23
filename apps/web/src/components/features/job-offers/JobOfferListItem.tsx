import { useState } from 'react';
import { JobOfferStatus, ContractType, PaymentStatus } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { JobOfferForm } from '@/components/features/job-offers/JobOfferForm';
import { ApplicationsForOfferSection } from '@/components/features/job-offers/ApplicationsForOfferSection';
import { ApiError } from '@/lib/api/client';
import { WORK_MODE_LABELS } from '@/lib/work-mode-labels';
import { type JobOffer, type JobOfferInput } from '@/lib/api/job-offers';
import { formatCompensation } from '@/lib/format-compensation';

const STATUS_LABELS: Record<string, string> = {
  [JobOfferStatus.DRAFT]: 'Brouillon',
  [JobOfferStatus.PUBLISHED]: 'Publiée',
  // « Expirée » seul evoque une faute du client et n'indique aucun remede.
  [JobOfferStatus.EXPIRED]: 'Publication terminée',
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
  // Publication gratuite (Jeuncy gratuit pour les entreprises depuis le
  // 2026-09-15) : un seul chemin, les variantes essai/abonnement/paiement
  // ont disparu de ce composant.
  onPublish: (id: number) => Promise<unknown>;
  isSubmitting: boolean;
  isPublishing: boolean;
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
}: JobOfferListItemProps) {
  const [isEditing, setIsEditing] = useState(false);
  const [showApplications, setShowApplications] = useState(false);
  const [updateError, setUpdateError] = useState<string | null>(null);

  // Miroir exact de JobOfferService::requireOwnedEditableOffer. Une offre
  // payee, en essai ou par abonnement reste fermee a la modification ; depuis
  // que Jeuncy est gratuit, toute nouvelle offre publiee est FREE, donc
  // modifiable.
  const isEditable =
    offer.status === JobOfferStatus.DRAFT ||
    (offer.status === JobOfferStatus.PUBLISHED &&
      offer.payment_status === PaymentStatus.FREE);

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
              // Une offre en fin de publication appelle une action, une offre
              // archivee est un etat de repos volontaire : c'est la premiere
              // qui doit attirer l'oeil.
              offer.status === JobOfferStatus.PUBLISHED
                ? 'default'
                : offer.status === JobOfferStatus.EXPIRED
                  ? 'destructive'
                  : 'secondary'
            }
          >
            {STATUS_LABELS[offer.status]}
          </Badge>
          {/* Vestige du modele paye : une offre achetee avant la gratuite
              porte encore une echeance, et ExpireJobOffers l'honorera. Elle
              pourra ensuite etre remise en ligne gratuitement. */}
          {offer.status === JobOfferStatus.PUBLISHED && offer.expires_at && (
            <span className="font-inter text-xs text-muted-foreground">
              En ligne jusqu’au {new Date(offer.expires_at).toLocaleDateString('fr-FR')}
            </span>
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
          <Button
            type="button"
            variant="gradient"
            size="sm"
            onClick={() => void onPublish(offer.id)}
            disabled={isPublishing}
          >
            {isPublishing ? 'Publication…' : 'Publier — gratuit'}
          </Button>
        )}
        {/* « Modifier » suit la regle du serveur (requireOwnedEditableOffer) :
            un brouillon, OU une offre publiee gratuitement. Le bouton n'etait
            montre que sur les brouillons, ce qui rendait toute offre en ligne
            definitivement figee — impossible d'y ajouter le code postal, donc
            impossible de la faire entrer dans « Decouvrir », et impossible de
            completer une offre express, qui est publiee des sa creation.
            L'API acceptait ces modifications depuis le lot 1 ; seul le bouton
            manquait. */}
        {isEditable && (
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => setIsEditing(true)}
          >
            Modifier
          </Button>
        )}
        {/* Retour en ligne : une offre retiree a la fin d'un ancien essai
            (archivee, TRIAL) ou arrivee au bout d'une ancienne periode payee
            (EXPIRED). Memes etats que cote serveur (requirePayableOffer) ;
            une offre archivee a la main n'a pas ce bouton. */}
        {((offer.status === JobOfferStatus.ARCHIVED &&
          offer.payment_status === PaymentStatus.TRIAL) ||
          offer.status === JobOfferStatus.EXPIRED) && (
          <Button
            type="button"
            variant="gradient"
            size="sm"
            onClick={() => void onPublish(offer.id)}
            disabled={isPublishing}
          >
            {isPublishing ? 'Publication…' : 'Remettre en ligne — gratuit'}
          </Button>
        )}
        {/* Pas d'archivage sur une offre echue : elle est deja hors ligne,
            et l'archiver lui oterait le bouton de remise en ligne (garde
            equivalente cote serveur dans JobOfferService::archiveForUser). */}
        {offer.status !== JobOfferStatus.ARCHIVED &&
          offer.status !== JobOfferStatus.EXPIRED && (
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
