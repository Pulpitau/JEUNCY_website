import { Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ApplicationStatus } from '@jeuncy/shared';
import { Video, Briefcase, Linkedin, Lock } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
  listApplicationsForOffer,
  updateApplicationStatus,
} from '@/lib/api/applications';
import { ApiError } from '@/lib/api/client';

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
    // Un 402 (candidatures verrouillees) n'est pas une erreur transitoire a
    // reessayer automatiquement — evite 3 tentatives inutiles avant
    // d'afficher le paywall.
    retry: (failureCount, error) =>
      !(error instanceof ApiError && error.status === 402) && failureCount < 3,
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

  if (
    applicationsQuery.isError &&
    applicationsQuery.error instanceof ApiError &&
    applicationsQuery.error.status === 402
  ) {
    return (
      // Plus de deblocage a l'offre depuis le 2026-08-17 : le seul chemin vers
      // les candidatures est l'abonnement, on renvoie donc directement vers
      // lui plutot que de proposer un achat qui n'existe plus.
      <div className="flex flex-col items-start gap-3 rounded-md border border-jeuncy-orange/30 bg-jeuncy-orange/10 p-4">
        <div className="flex items-center gap-2 font-poppins text-sm font-medium text-foreground">
          <Lock className="h-4 w-4 text-jeuncy-orange" aria-hidden="true" />
          Candidatures verrouillées
        </div>
        <p className="font-inter text-sm text-muted-foreground">
          L'accès aux candidatures est inclus dans l'abonnement, avec la publication
          illimitée d'offres et la CVthèque pour contacter directement les profils qui
          vous intéressent.
        </p>
        <Link
          to="/tarifs"
          className={cn(buttonVariants({ variant: 'gradient', size: 'sm' }))}
        >
          Voir l'abonnement
        </Link>
      </div>
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

          <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 font-inter text-sm text-foreground">
            {application.contact_phone && (
              <a href={`tel:${application.contact_phone}`} className="hover:underline">
                {application.contact_phone}
              </a>
            )}
            <a
              href={`mailto:${application.candidate_profile.user.email}`}
              className="hover:underline"
            >
              {application.candidate_profile.user.email}
            </a>
            {(application.generated_cv?.file_url ?? application.cv_file_url) && (
              <a
                href={application.generated_cv?.file_url ?? application.cv_file_url!}
                target="_blank"
                rel="noreferrer"
                className="text-primary hover:underline"
              >
                Voir le CV
              </a>
            )}
            {application.candidate_profile.video_url && (
              <a
                href={application.candidate_profile.video_url}
                target="_blank"
                rel="noreferrer"
                className="flex items-center gap-1 text-primary hover:underline"
              >
                <Video className="h-3.5 w-3.5" aria-hidden="true" />
                Vidéo de présentation
              </a>
            )}
            {application.candidate_profile.portfolio_url && (
              <a
                href={application.candidate_profile.portfolio_url}
                target="_blank"
                rel="noreferrer"
                className="flex items-center gap-1 text-primary hover:underline"
              >
                <Briefcase className="h-3.5 w-3.5" aria-hidden="true" />
                Portfolio
              </a>
            )}
            {application.candidate_profile.linkedin_url && (
              <a
                href={application.candidate_profile.linkedin_url}
                target="_blank"
                rel="noreferrer"
                className="flex items-center gap-1 text-primary hover:underline"
              >
                <Linkedin className="h-3.5 w-3.5" aria-hidden="true" />
                LinkedIn
              </a>
            )}
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
