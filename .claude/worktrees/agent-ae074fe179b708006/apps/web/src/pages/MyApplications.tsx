import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { ApplicationStatus } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from '@/components/ui/card';
import { listMyApplications } from '@/lib/api/applications';

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
  const applicationsQuery = useQuery({
    queryKey: ['applications', 'mine'],
    queryFn: listMyApplications,
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

      {applicationsQuery.isLoading ? (
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
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
              {application.cover_letter && (
                <CardContent>
                  <p className="font-inter text-sm text-muted-foreground">
                    {application.cover_letter}
                  </p>
                </CardContent>
              )}
            </Card>
          ))}
        </div>
      )}
    </main>
  );
}
