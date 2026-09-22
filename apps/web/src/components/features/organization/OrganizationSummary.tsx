import { Building2, BadgeCheck, Clock, XCircle } from 'lucide-react';
import { VerificationStatus, type WorkMode } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { WORK_MODE_LABELS } from '@/lib/work-mode-labels';

interface OrganizationSummaryProps {
  name: string;
  logoUrl?: string | null;
  siret?: string | null;
  verificationStatus?: VerificationStatus | null;
  verificationNote?: string | null;
  ndaNumber?: string | null;
  qualiopiNumber?: string | null;
  city?: string | null;
  website?: string | null;
  description?: string | null;
  diplomasOffered?: string | null;
  diplomaLevel?: string | null;
  workMode?: WorkMode | null;
  onEdit: () => void;
}

export function OrganizationSummary({
  name,
  logoUrl,
  siret,
  verificationStatus,
  verificationNote,
  ndaNumber,
  qualiopiNumber,
  city,
  website,
  description,
  diplomasOffered,
  diplomaLevel,
  workMode,
  onEdit,
}: OrganizationSummaryProps) {
  const rows = [
    siret ? { label: 'SIRET', value: siret } : null,
    ndaNumber ? { label: 'Numéro NDA', value: ndaNumber } : null,
    qualiopiNumber ? { label: 'Certification QUALIOPI', value: qualiopiNumber } : null,
    city ? { label: 'Ville', value: city } : null,
    diplomaLevel ? { label: 'Niveau de diplôme principal', value: diplomaLevel } : null,
    workMode ? { label: 'Mode', value: WORK_MODE_LABELS[workMode] } : null,
    website ? { label: 'Site web', value: website, isLink: true } : null,
  ].filter((row): row is { label: string; value: string; isLink?: boolean } => !!row);

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-start justify-between gap-4">
        <div className="flex items-center gap-3">
          {logoUrl ? (
            <img
              src={logoUrl}
              alt=""
              className="h-12 w-12 shrink-0 rounded-md border border-border object-contain p-0.5"
            />
          ) : (
            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-md border border-border bg-muted">
              <Building2 className="h-5 w-5 text-muted-foreground" aria-hidden="true" />
            </div>
          )}
          <div className="flex flex-col gap-1.5">
            <p className="font-poppins text-lg font-semibold text-foreground">{name}</p>
            {/* Statut de vérification : ce qui commande l'accès aux candidats
                (MOBILE.md §4.0). Tant qu'il n'est pas VERIFIED, ni CVthèque,
                ni candidatures reçues — autant que le titulaire le sache ici
                plutôt qu'en butant sur un 403. */}
            {verificationStatus === VerificationStatus.VERIFIED && (
              <Badge variant="secondary" className="w-fit gap-1">
                <BadgeCheck className="h-3.5 w-3.5" aria-hidden="true" />
                Entreprise vérifiée
              </Badge>
            )}
            {verificationStatus === VerificationStatus.PENDING && (
              <Badge variant="outline" className="w-fit gap-1">
                <Clock className="h-3.5 w-3.5" aria-hidden="true" />
                En attente de vérification
              </Badge>
            )}
            {verificationStatus === VerificationStatus.REJECTED && (
              <Badge variant="destructive" className="w-fit gap-1">
                <XCircle className="h-3.5 w-3.5" aria-hidden="true" />
                Vérification refusée
              </Badge>
            )}
          </div>
        </div>
        <Button type="button" variant="outline" size="sm" onClick={onEdit}>
          Modifier
        </Button>
      </div>

      {verificationNote && verificationStatus !== VerificationStatus.VERIFIED && (
        <p
          role="status"
          className="rounded-md border border-border bg-muted/40 p-3 font-inter text-sm text-muted-foreground"
        >
          {verificationNote}
        </p>
      )}

      {rows.length > 0 && (
        <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          {rows.map((row) => (
            <div key={row.label}>
              <dt className="font-inter text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {row.label}
              </dt>
              <dd className="font-inter text-sm text-foreground">
                {row.isLink ? (
                  <a
                    href={row.value}
                    target="_blank"
                    rel="noreferrer"
                    className="text-primary hover:underline"
                  >
                    {row.value}
                  </a>
                ) : (
                  row.value
                )}
              </dd>
            </div>
          ))}
        </dl>
      )}

      {description && (
        <div>
          <dt className="font-inter text-xs font-medium uppercase tracking-wide text-muted-foreground">
            Description
          </dt>
          <dd className="mt-1 whitespace-pre-line font-inter text-sm text-foreground">
            {description}
          </dd>
        </div>
      )}

      {diplomasOffered && (
        <div>
          <dt className="font-inter text-xs font-medium uppercase tracking-wide text-muted-foreground">
            Diplômes et formations proposés
          </dt>
          <dd className="mt-1 whitespace-pre-line font-inter text-sm text-foreground">
            {diplomasOffered}
          </dd>
        </div>
      )}
    </div>
  );
}
