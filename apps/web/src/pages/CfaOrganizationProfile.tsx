import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { GraduationCap } from 'lucide-react';
import { ContractType } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from '@/components/ui/card';
import { getPublicCfaOrganization } from '@/lib/api/cfa-organizations';
import { ApiError } from '@/lib/api/client';
import { WORK_MODE_LABELS } from '@/lib/work-mode-labels';

const CONTRACT_TYPE_LABELS: Record<string, string> = {
  [ContractType.ALTERNANCE]: 'Alternance',
  [ContractType.SAISONNIER]: 'Saisonnier',
  [ContractType.BENEVOLAT]: 'Bénévolat',
  [ContractType.JOB_ETUDIANT]: 'Job étudiant',
  [ContractType.STAGE]: 'Stage',
};

export function CfaOrganizationProfile() {
  const { id } = useParams<{ id: string }>();
  const cfaId = Number(id);

  const cfaQuery = useQuery({
    queryKey: ['cfa-organizations', 'public', cfaId],
    queryFn: () => getPublicCfaOrganization(cfaId),
    retry: false,
    enabled: Number.isFinite(cfaId),
  });

  if (cfaQuery.isLoading) {
    return (
      <main className="mx-auto max-w-3xl px-4 py-12">
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      </main>
    );
  }

  if (cfaQuery.isError) {
    const message =
      cfaQuery.error instanceof ApiError
        ? cfaQuery.error.message
        : 'Ce CFA est introuvable.';

    return (
      <main className="mx-auto max-w-3xl px-4 py-12">
        <p role="alert" className="font-inter text-sm text-destructive">
          {message}
        </p>
        <Link
          to="/cfa"
          className="mt-4 inline-block text-sm text-primary hover:underline"
        >
          ← Retour aux CFA
        </Link>
      </main>
    );
  }

  const cfa = cfaQuery.data!;

  return (
    <main className="mx-auto max-w-3xl px-4 py-12">
      <Link to="/cfa" className="text-sm text-primary hover:underline">
        ← Retour aux CFA
      </Link>

      <Card className="mt-4">
        <CardHeader>
          <div className="flex items-center gap-3">
            {cfa.logo_url ? (
              <img
                src={cfa.logo_url}
                alt=""
                className="h-14 w-14 shrink-0 rounded-md border border-border object-contain p-1"
              />
            ) : (
              <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-md border border-border bg-muted">
                <GraduationCap
                  className="h-6 w-6 text-muted-foreground"
                  aria-hidden="true"
                />
              </div>
            )}
            <CardTitle className="text-2xl">{cfa.name}</CardTitle>
          </div>
          <CardDescription>
            {cfa.city}
            {cfa.website && (
              <>
                {' · '}
                <a
                  href={cfa.website}
                  target="_blank"
                  rel="noreferrer"
                  className="text-primary hover:underline"
                >
                  {cfa.website}
                </a>
              </>
            )}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {cfa.description && (
            <p className="whitespace-pre-line font-inter text-sm leading-relaxed text-foreground">
              {cfa.description}
            </p>
          )}

          {cfa.diplomas_offered && (
            <div className="mt-6">
              <h2 className="font-poppins text-lg font-semibold text-foreground">
                Diplômes et formations proposés
              </h2>
              <p className="mt-2 whitespace-pre-line font-inter text-sm leading-relaxed text-foreground">
                {cfa.diplomas_offered}
              </p>
            </div>
          )}

          {(cfa.diploma_level || cfa.training_mode) && (
            <div className="mt-4 flex flex-wrap gap-2">
              {cfa.diploma_level && <Badge variant="outline">{cfa.diploma_level}</Badge>}
              {cfa.training_mode && (
                <Badge variant="outline">{WORK_MODE_LABELS[cfa.training_mode]}</Badge>
              )}
            </div>
          )}

          {(cfa.siret || cfa.nda_number || cfa.qualiopi_number) && (
            <div className="mt-6 rounded-md border border-border p-4">
              <h2 className="font-poppins text-sm font-semibold text-foreground">
                Informations légales
              </h2>
              <dl className="mt-2 grid grid-cols-1 gap-2 font-inter text-sm sm:grid-cols-3">
                {cfa.siret && (
                  <div>
                    <dt className="text-xs uppercase tracking-wide text-muted-foreground">
                      SIRET
                    </dt>
                    <dd className="text-foreground">{cfa.siret}</dd>
                  </div>
                )}
                {cfa.nda_number && (
                  <div>
                    <dt className="text-xs uppercase tracking-wide text-muted-foreground">
                      Numéro NDA
                    </dt>
                    <dd className="text-foreground">{cfa.nda_number}</dd>
                  </div>
                )}
                {cfa.qualiopi_number && (
                  <div>
                    <dt className="text-xs uppercase tracking-wide text-muted-foreground">
                      Certification QUALIOPI
                    </dt>
                    <dd className="text-foreground">{cfa.qualiopi_number}</dd>
                  </div>
                )}
              </dl>
            </div>
          )}

          <div className="mt-8">
            <h2 className="font-poppins text-lg font-semibold text-foreground">
              Offres publiées
            </h2>
            {cfa.job_offers.length === 0 ? (
              <p className="mt-2 font-inter text-sm text-muted-foreground">
                Aucune offre publiée pour l'instant.
              </p>
            ) : (
              <div className="mt-3 flex flex-col gap-3">
                {cfa.job_offers.map((offer) => (
                  <Link
                    key={offer.id}
                    to={`/offres/${offer.id}`}
                    className="rounded-md border border-border p-4 transition-colors hover:border-primary"
                  >
                    <Badge variant="outline" className="w-fit">
                      {CONTRACT_TYPE_LABELS[offer.contract_type]}
                    </Badge>
                    <p className="mt-2 font-poppins font-medium">{offer.title}</p>
                    {offer.city && (
                      <p className="text-sm text-muted-foreground">{offer.city}</p>
                    )}
                  </Link>
                ))}
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    </main>
  );
}
