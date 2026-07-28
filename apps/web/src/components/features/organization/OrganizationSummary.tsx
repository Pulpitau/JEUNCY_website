import { Button } from '@/components/ui/button';

interface OrganizationSummaryProps {
  name: string;
  siret?: string | null;
  city?: string | null;
  website?: string | null;
  description?: string | null;
  onEdit: () => void;
}

export function OrganizationSummary({
  name,
  siret,
  city,
  website,
  description,
  onEdit,
}: OrganizationSummaryProps) {
  const rows = [
    siret ? { label: 'SIRET', value: siret } : null,
    city ? { label: 'Ville', value: city } : null,
    website ? { label: 'Site web', value: website, isLink: true } : null,
  ].filter((row): row is { label: string; value: string; isLink?: boolean } => !!row);

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-start justify-between gap-4">
        <p className="font-poppins text-lg font-semibold text-foreground">{name}</p>
        <Button type="button" variant="outline" size="sm" onClick={onEdit}>
          Modifier
        </Button>
      </div>

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
    </div>
  );
}
