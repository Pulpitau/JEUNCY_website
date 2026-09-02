import { Video, Briefcase, Linkedin } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { CandidateProfile } from '@/lib/api/candidate-profile';
import { getYoutubeEmbedUrl } from '@/lib/youtube';

interface ProfileInfoSummaryProps {
  profile: CandidateProfile;
  onEdit: () => void;
}

function formatBirthDate(value: string | null): string | null {
  if (!value) return null;
  return new Date(value).toLocaleDateString('fr-FR');
}

const DEFINED_ROWS = (
  profile: CandidateProfile,
): Array<{ label: string; value: string }> => {
  const rows: Array<{ label: string; value: string | null }> = [
    { label: 'Ce que tu recherches', value: profile.headline },
    { label: 'Téléphone', value: profile.phone },
    { label: 'Date de naissance', value: formatBirthDate(profile.birth_date) },
    { label: 'Adresse', value: profile.address },
    {
      label: 'Ville',
      value: [profile.postal_code, profile.city].filter(Boolean).join(' ') || null,
    },
    { label: 'Permis de conduire', value: profile.driving_license },
  ];

  return rows.filter((row): row is { label: string; value: string } => !!row.value);
};

export function ProfileInfoSummary({ profile, onEdit }: ProfileInfoSummaryProps) {
  const rows = DEFINED_ROWS(profile);

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-start justify-between gap-4">
        <p className="font-poppins text-lg font-semibold text-foreground">
          {profile.first_name} {profile.last_name}
        </p>
        <Button type="button" variant="outline" size="sm" onClick={onEdit}>
          Modifier
        </Button>
      </div>

      {rows.length > 0 ? (
        <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          {rows.map((row) => (
            <div key={row.label}>
              <dt className="font-inter text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {row.label}
              </dt>
              <dd className="font-inter text-sm text-foreground">{row.value}</dd>
            </div>
          ))}
        </dl>
      ) : (
        <p className="font-inter text-sm text-muted-foreground">
          Complète ces informations en cliquant sur "Modifier".
        </p>
      )}

      {profile.bio && (
        <div>
          <dt className="font-inter text-xs font-medium uppercase tracking-wide text-muted-foreground">
            Bio
          </dt>
          <dd className="mt-1 whitespace-pre-line font-inter text-sm text-foreground">
            {profile.bio}
          </dd>
        </div>
      )}

      {profile.hobbies && (
        <div>
          <dt className="font-inter text-xs font-medium uppercase tracking-wide text-muted-foreground">
            Loisirs
          </dt>
          <dd className="mt-1 font-inter text-sm text-foreground">{profile.hobbies}</dd>
        </div>
      )}

      {(profile.video_url || profile.portfolio_url || profile.linkedin_url) && (
        <div className="flex flex-col gap-3">
          <dt className="font-inter text-xs font-medium uppercase tracking-wide text-muted-foreground">
            Liens
          </dt>
          <div className="flex flex-wrap gap-4 font-inter text-sm">
            {profile.portfolio_url && (
              <a
                href={profile.portfolio_url}
                target="_blank"
                rel="noreferrer"
                className="flex items-center gap-1.5 text-primary hover:underline"
              >
                <Briefcase className="h-4 w-4" aria-hidden="true" />
                Portfolio
              </a>
            )}
            {profile.linkedin_url && (
              <a
                href={profile.linkedin_url}
                target="_blank"
                rel="noreferrer"
                className="flex items-center gap-1.5 text-primary hover:underline"
              >
                <Linkedin className="h-4 w-4" aria-hidden="true" />
                LinkedIn
              </a>
            )}
            {profile.video_url && !getYoutubeEmbedUrl(profile.video_url) && (
              <a
                href={profile.video_url}
                target="_blank"
                rel="noreferrer"
                className="flex items-center gap-1.5 text-primary hover:underline"
              >
                <Video className="h-4 w-4" aria-hidden="true" />
                Vidéo de présentation
              </a>
            )}
          </div>

          {profile.video_url && getYoutubeEmbedUrl(profile.video_url) && (
            <div className="aspect-video w-full max-w-md overflow-hidden rounded-md border border-border">
              <iframe
                src={getYoutubeEmbedUrl(profile.video_url)!}
                title="Vidéo de présentation"
                className="h-full w-full"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowFullScreen
              />
            </div>
          )}
        </div>
      )}
    </div>
  );
}
