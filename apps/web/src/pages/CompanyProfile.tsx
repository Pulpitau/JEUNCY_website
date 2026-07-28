import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { ContractType } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from '@/components/ui/card';
import { getPublicCompany } from '@/lib/api/companies';
import { ApiError } from '@/lib/api/client';

const CONTRACT_TYPE_LABELS: Record<string, string> = {
  [ContractType.ALTERNANCE]: 'Alternance',
  [ContractType.SAISONNIER]: 'Saisonnier',
  [ContractType.BENEVOLAT]: 'Bénévolat',
};

export function CompanyProfile() {
  const { id } = useParams<{ id: string }>();
  const companyId = Number(id);

  const companyQuery = useQuery({
    queryKey: ['companies', 'public', companyId],
    queryFn: () => getPublicCompany(companyId),
    retry: false,
    enabled: Number.isFinite(companyId),
  });

  if (companyQuery.isLoading) {
    return (
      <main className="mx-auto max-w-3xl px-4 py-12">
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      </main>
    );
  }

  if (companyQuery.isError) {
    const message =
      companyQuery.error instanceof ApiError
        ? companyQuery.error.message
        : 'Cette entreprise est introuvable.';

    return (
      <main className="mx-auto max-w-3xl px-4 py-12">
        <p role="alert" className="font-inter text-sm text-destructive">
          {message}
        </p>
        <Link
          to="/entreprises"
          className="mt-4 inline-block text-sm text-primary hover:underline"
        >
          ← Retour aux entreprises
        </Link>
      </main>
    );
  }

  const company = companyQuery.data!;

  return (
    <main className="mx-auto max-w-3xl px-4 py-12">
      <Link to="/entreprises" className="text-sm text-primary hover:underline">
        ← Retour aux entreprises
      </Link>

      <Card className="mt-4">
        <CardHeader>
          <CardTitle className="text-2xl">{company.name}</CardTitle>
          <CardDescription>
            {company.city}
            {company.website && (
              <>
                {' · '}
                <a
                  href={company.website}
                  target="_blank"
                  rel="noreferrer"
                  className="text-primary hover:underline"
                >
                  {company.website}
                </a>
              </>
            )}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {company.description && (
            <p className="whitespace-pre-line font-inter text-sm leading-relaxed text-foreground">
              {company.description}
            </p>
          )}

          <div className="mt-8">
            <h2 className="font-poppins text-lg font-semibold text-foreground">
              Offres publiées
            </h2>
            {company.job_offers.length === 0 ? (
              <p className="mt-2 font-inter text-sm text-muted-foreground">
                Aucune offre publiée pour l'instant.
              </p>
            ) : (
              <div className="mt-3 flex flex-col gap-3">
                {company.job_offers.map((offer) => (
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
