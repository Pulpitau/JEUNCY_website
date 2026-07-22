import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { JobOfferStatus } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { archiveJobOfferAsAdmin, listAdminJobOffers } from '@/lib/api/admin';
import { AdminPager } from './AdminPager';

const STATUS_OPTIONS = [
  { value: '', label: 'Tous les statuts' },
  { value: JobOfferStatus.DRAFT, label: 'Brouillon' },
  { value: JobOfferStatus.PUBLISHED, label: 'Publiée' },
  { value: JobOfferStatus.EXPIRED, label: 'Expirée' },
  { value: JobOfferStatus.ARCHIVED, label: 'Archivée' },
];

export function AdminJobOffersPanel() {
  const queryClient = useQueryClient();
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);

  const offersQuery = useQuery({
    queryKey: ['admin', 'job-offers', { status, page }],
    queryFn: () =>
      listAdminJobOffers({ status: (status as JobOfferStatus) || undefined, page }),
  });

  const archiveMutation = useMutation({
    mutationFn: archiveJobOfferAsAdmin,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'job-offers'] }),
  });

  const offers = offersQuery.data?.data ?? [];
  const lastPage = offersQuery.data?.last_page ?? 1;

  return (
    <div className="flex flex-col gap-4">
      <select
        className={cn(
          'flex h-10 w-full max-w-xs rounded-md border border-input bg-background px-3 py-2 text-sm font-inter focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
        )}
        value={status}
        onChange={(event) => {
          setStatus(event.target.value);
          setPage(1);
        }}
      >
        {STATUS_OPTIONS.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>

      {offersQuery.isLoading ? (
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      ) : offersQuery.isError ? (
        <p role="alert" className="font-inter text-sm text-destructive">
          Impossible de charger les offres pour le moment, réessaie plus tard.
        </p>
      ) : offers.length === 0 ? (
        <p className="font-inter text-sm text-muted-foreground">Aucune offre.</p>
      ) : (
        <div className="flex flex-col gap-3">
          {offers.map((offer) => (
            <div
              key={offer.id}
              className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border p-4"
            >
              <div>
                <p className="font-poppins font-medium">{offer.title}</p>
                <p className="text-xs text-muted-foreground">
                  {(offer.company ?? offer.cfa_organization)?.name}
                  {offer.city ? ` — ${offer.city}` : ''}
                </p>
              </div>
              <div className="flex items-center gap-2">
                <Badge variant="outline">{offer.status}</Badge>
                {offer.status !== JobOfferStatus.ARCHIVED && (
                  <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    disabled={archiveMutation.isPending}
                    onClick={() => archiveMutation.mutate(offer.id)}
                  >
                    Archiver
                  </Button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      <AdminPager page={page} lastPage={lastPage} onChange={setPage} />
    </div>
  );
}
