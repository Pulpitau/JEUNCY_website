import { Button } from '@/components/ui/button';
import type { CandidateProfile } from '@/lib/api/candidate-profile';

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
    { label: 'Titre professionnel', value: profile.headline },
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
    </div>
  );
}
