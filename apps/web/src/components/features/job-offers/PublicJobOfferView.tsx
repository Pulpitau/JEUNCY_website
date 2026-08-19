import type { ReactNode } from 'react';
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
import type { PublicJobOffer } from '@/lib/api/job-offers';
import { WORK_MODE_LABELS } from '@/lib/work-mode-labels';
import { formatCompensation } from '@/lib/format-compensation';

const CONTRACT_TYPE_LABELS: Record<string, string> = {
  [ContractType.ALTERNANCE]: 'Alternance',
  [ContractType.SAISONNIER]: 'Saisonnier',
  [ContractType.BENEVOLAT]: 'Bénévolat',
  [ContractType.JOB_ETUDIANT]: 'Job étudiant',
  [ContractType.STAGE]: 'Stage',
};

interface PublicJobOfferViewProps {
  offer: PublicJobOffer;
  // Bloc rendu en bas de la carte : la page publique y met le formulaire de
  // candidature, l'apercu admin n'y met rien. Un slot plutot qu'un booleen
  // pour que ce composant reste purement presentationnel.
  footer?: ReactNode;
}

// Rendu public d'une offre, extrait de JobOfferDetail pour etre partage avec
// l'apercu back-office (AdminJobOfferPreview) : l'equipe doit voir EXACTEMENT
// ce que verra un visiteur, donc un seul JSX pour les deux pages. Aucun fetch
// ici — chaque page apporte son offre et son slot.
export function PublicJobOfferView({ offer, footer }: PublicJobOfferViewProps) {
  const publisher = offer.company ?? offer.cfa_organization;
  const isCfaOffer = offer.cfa_organization_id !== null;
  const skillsLabel = isCfaOffer
    ? 'Compétences et expériences acquises'
    : 'Compétences recherchées';

  return (
    <Card className="mt-4">
      <CardHeader>
        <Badge variant="outline" className="w-fit">
          {CONTRACT_TYPE_LABELS[offer.contract_type]}
        </Badge>
        <CardTitle className="text-2xl">{offer.title}</CardTitle>
        <div className="flex items-center gap-2">
          {publisher?.logo_url ? (
            <img
              src={publisher.logo_url}
              alt=""
              className="h-8 w-8 shrink-0 rounded border border-border object-contain"
            />
          ) : (
            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded border border-border bg-muted">
              <Building2 className="h-4 w-4 text-muted-foreground" aria-hidden="true" />
            </div>
          )}
          <CardDescription>
            {publisher?.name}
            {offer.city ? ` · ${offer.city}` : ''}
          </CardDescription>
        </div>
      </CardHeader>
      <CardContent>
        <dl className="mb-4 flex flex-col gap-1 font-inter text-sm font-medium text-foreground">
          {offer.work_mode && (
            <div>
              <dt className="inline text-muted-foreground">Type d'offre : </dt>
              <dd className="inline">{WORK_MODE_LABELS[offer.work_mode]}</dd>
            </div>
          )}
          {formatCompensation(offer.compensation_amount, offer.compensation_period) && (
            <div>
              <dt className="inline text-muted-foreground">Rémunération : </dt>
              <dd className="inline">
                {formatCompensation(offer.compensation_amount, offer.compensation_period)}
              </dd>
            </div>
          )}
          {!isCfaOffer && offer.experience_level && (
            <div>
              <dt className="inline text-muted-foreground">Expérience requise : </dt>
              <dd className="inline">{offer.experience_level}</dd>
            </div>
          )}
          {isCfaOffer && offer.diploma_level && (
            <div>
              <dt className="inline text-muted-foreground">Niveau visé : </dt>
              <dd className="inline">{offer.diploma_level}</dd>
            </div>
          )}
          {isCfaOffer && offer.training_rhythm && (
            <div>
              <dt className="inline text-muted-foreground">Rythme de l'alternance : </dt>
              <dd className="inline">{offer.training_rhythm}</dd>
            </div>
          )}
        </dl>

        <p className="whitespace-pre-line font-inter text-sm leading-relaxed text-foreground">
          {offer.description}
        </p>

        {offer.skills.length > 0 && (
          <div className="mt-4">
            <p className="mb-2 font-poppins text-sm font-medium text-foreground">
              {skillsLabel}
            </p>
            <div className="flex flex-wrap gap-1">
              {offer.skills.map((skill) => (
                <Badge key={skill.id} variant="secondary">
                  {skill.name}
                </Badge>
              ))}
            </div>
          </div>
        )}

        {!isCfaOffer && offer.benefits && (
          <div className="mt-4">
            <p className="mb-1 font-poppins text-sm font-medium text-foreground">
              Avantages
            </p>
            <p className="whitespace-pre-line font-inter text-sm text-muted-foreground">
              {offer.benefits}
            </p>
          </div>
        )}

        {footer}
      </CardContent>
    </Card>
  );
}
