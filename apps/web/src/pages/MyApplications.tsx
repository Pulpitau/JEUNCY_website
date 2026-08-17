import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ApplicationStatus } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from '@/components/ui/card';
import { listMyApplications, withdrawApplication } from '@/lib/api/applications';
import { ApiError } from '@/lib/api/client';

const APPLICATIONS_QUERY_KEY = ['applications', 'mine'];

const STATUS_LABELS: Record<string, string> = {
  [ApplicationStatus.SENT]: 'Envoyée',
  [ApplicationStatus.SEEN]: 'Vue',
  [ApplicationStatus.INTERVIEW]: 'Entretien',
  [ApplicationStatus.ACCEPTED]: 'Acceptée',
  [ApplicationStatus.REJECTED]: 'Refusée',
};

function statusVariant(
  status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
  if (status === ApplicationStatus.ACCEPTED) return 'default';
  if (status === ApplicationStatus.REJECTED) return 'destructive';
  if (status === ApplicationStatus.INTERVIEW) return 'secondary';
  return 'outline';
}

export function MyApplications() {
  const queryClient = useQueryClient();
  const [withdrawError, setWithdrawError] = useState<string | null>(null);
  const applicationsQuery = useQuery({
    queryKey: APPLICATIONS_QUERY_KEY,
    queryFn: listMyApplications,
  });
  const withdrawMutation = useMutation({
    mutationFn: withdrawApplication,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: APPLICATIONS_QUERY_KEY }),
    onError: (error) => {
      setWithdrawError(
        error instanceof ApiError
          ? error.message
          : 'Impossible de retirer la candidature pour le moment.',
      );
    },
  });

  const applications = applicationsQuery.data ?? [];

  return (
    <main className="mx-auto flex max-w-3xl flex-col gap-6 px-4 py-12">
      <div>
        <h1 className="font-poppins text-3xl font-bold">Mes candidatures</h1>
        <p className="mt-1 font-inter text-muted-foreground">
          Suis l'avancement de tes candidatures aux offres Jeuncy.
        </p>
      </div>

      {withdrawError && (
        <p role="alert" className="font-inter text-sm text-destructive">
          {withdrawError}
        </p>
      )}

      {applicationsQuery.isLoading ? (
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      ) : applicationsQuery.isError ? (
        <p role="alert" className="font-inter text-sm text-destructive">
          Impossible de charger tes candidatures pour le moment, réessaie plus tard.
        </p>
      ) : applications.length === 0 ? (
        <p className="font-inter text-sm text-muted-foreground">
          Tu n'as pas encore postulé à une offre.{' '}
          <Link to="/offres" className="text-primary hover:underline">
            Découvrir les offres
          </Link>
          .
        </p>
      ) : (
        <div className="flex flex-col gap-4">
          {applications.map((application) => (
            <Card key={application.id}>
              <CardHeader>
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <CardTitle>
                      <Link
                        to={`/offres/${application.job_offer.id}`}
                        className="hover:underline"
                      >
                        {application.job_offer.title}
                      </Link>
                    </CardTitle>
                    <CardDescription>{application.job_offer.city ?? ''}</CardDescription>
                  </div>
                  <Badge variant={statusVariant(application.status)}>
                    {STATUS_LABELS[application.status]}
                  </Badge>
                </div>
              </CardHeader>
              {(application.cover_letter ||
                application.generated_cv ||
                application.cv_file_url) && (
                <CardContent className="flex flex-col gap-2">
                  {application.cover_letter && (
                    <p className="font-inter text-sm text-muted-foreground">
                      {application.cover_letter}
                    </p>
                  )}
                  {(application.generated_cv?.file_url ?? application.cv_file_url) && (
                    <a
                      href={
                        application.generated_cv?.file_url ?? application.cv_file_url!
                      }
                      target="_blank"
                      rel="noreferrer"
                      className="font-inter text-sm text-primary hover:underline"
                    >
                      Voir le CV envoyé
                    </a>
                  )}
                </CardContent>
              )}
              <CardContent className="pt-0">
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                  disabled={withdrawMutation.isPending}
                  onClick={() => {
                    if (
                      window.confirm(
                        `Retirer ta candidature pour « ${application.job_offer.title} » ? Cette action est irréversible.`,
                      )
                    ) {
                      setWithdrawError(null);
                      withdrawMutation.mutate(application.id);
                    }
                  }}
                >
                  {withdrawMutation.isPending ? 'Retrait…' : 'Retirer ma candidature'}
                </Button>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </main>
  );
}
