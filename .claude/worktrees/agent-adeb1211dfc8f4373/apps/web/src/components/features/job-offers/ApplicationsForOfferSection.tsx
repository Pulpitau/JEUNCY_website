import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ApplicationStatus } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import {
  listApplicationsForOffer,
  updateApplicationStatus,
} from '@/lib/api/applications';

const STATUS_LABELS: Record<string, string> = {
  [ApplicationStatus.SENT]: 'Envoyée',
  [ApplicationStatus.SEEN]: 'Vue',
  [ApplicationStatus.INTERVIEW]: 'Entretien',
  [ApplicationStatus.ACCEPTED]: 'Acceptée',
  [ApplicationStatus.REJECTED]: 'Refusée',
};

const UPDATABLE_STATUSES = [
  ApplicationStatus.SEEN,
  ApplicationStatus.INTERVIEW,
  ApplicationStatus.ACCEPTED,
  ApplicationStatus.REJECTED,
];

interface ApplicationsForOfferSectionProps {
  jobOfferId: number;
}

export function ApplicationsForOfferSection({
  jobOfferId,
}: ApplicationsForOfferSectionProps) {
  const queryClient = useQueryClient();
  const queryKey = ['job-offers', jobOfferId, 'applications'];

  const applicationsQuery = useQuery({
    queryKey,
    queryFn: () => listApplicationsForOffer(jobOfferId),
  });

  const updateStatusMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: ApplicationStatus }) =>
      updateApplicationStatus(id, status),
    onSuccess: () => queryClient.invalidateQueries({ queryKey }),
  });

  const applications = applicationsQuery.data ?? [];

  if (applicationsQuery.isLoading) {
    return (
      <p className="font-inter text-sm text-muted-foreground">
        Chargement des candidatures…
      </p>
    );
  }

  if (applications.length === 0) {
    return (
      <p className="font-inter text-sm text-muted-foreground">
        Aucune candidature reçue pour l'instant.
      </p>
    );
  }

  return (
    <div className="flex flex-col gap-3">
      {applications.map((application) => (
        <div key={application.id} className="rounded-md border border-border p-3">
          <div className="flex items-start justify-between gap-3">
            <div>
              <p className="font-poppins text-sm font-medium">
                {application.candidate_profile.first_name}{' '}
                {application.candidate_profile.last_name}
              </p>
              {application.candidate_profile.city && (
                <p className="text-xs text-muted-foreground">
                  {application.candidate_profile.city}
                </p>
              )}
            </div>
            <Badge variant="outline">{STATUS_LABELS[application.status]}</Badge>
          </div>

          {application.cover_letter && (
            <p className="mt-2 font-inter text-sm text-muted-foreground">
              {application.cover_letter}
            </p>
          )}

          <div className="mt-2 flex flex-col gap-1">
            <label
              htmlFor={`status-${application.id}`}
              className="text-xs font-medium text-muted-foreground"
            >
              Changer le statut
            </label>
            <select
              id={`status-${application.id}`}
              className={cn(
                'flex h-9 w-fit rounded-md border border-input bg-background px-3 py-1 text-sm font-inter focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
              )}
              value={application.status}
              disabled={updateStatusMutation.isPending}
              onChange={(event) =>
                updateStatusMutation.mutate({
                  id: application.id,
                  status: event.target.value as ApplicationStatus,
                })
              }
            >
              {!UPDATABLE_STATUSES.includes(application.status as never) && (
                <option value={application.status}>
                  {STATUS_LABELS[application.status]}
                </option>
              )}
              {UPDATABLE_STATUSES.map((status) => (
                <option key={status} value={status}>
                  {STATUS_LABELS[status]}
                </option>
              ))}
            </select>
          </div>
        </div>
      ))}
    </div>
  );
}
