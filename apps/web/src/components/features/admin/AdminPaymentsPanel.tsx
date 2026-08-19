import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { PaymentStatus } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { listAdminPayments, refundPayment } from '@/lib/api/admin';
import { ApiError } from '@/lib/api/client';
import { AdminPager } from './AdminPager';

const STATUS_OPTIONS = [
  { value: '', label: 'Tous les statuts' },
  { value: PaymentStatus.PENDING, label: 'En attente' },
  { value: PaymentStatus.SUCCEEDED, label: 'Réussi' },
  { value: PaymentStatus.FAILED, label: 'Échoué' },
  { value: PaymentStatus.REFUNDED, label: 'Remboursé' },
];

const STATUS_VARIANT: Record<
  string,
  'default' | 'destructive' | 'secondary' | 'outline'
> = {
  [PaymentStatus.SUCCEEDED]: 'default',
  [PaymentStatus.FAILED]: 'destructive',
  [PaymentStatus.REFUNDED]: 'secondary',
  [PaymentStatus.PENDING]: 'outline',
};

export function AdminPaymentsPanel() {
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  // Id du paiement dont le remboursement attend confirmation. Une seule
  // ligne a la fois : un remboursement deplace de l'argent reel et ne se
  // rattrape pas, il ne doit jamais partir sur un clic unique.
  const [pendingRefundId, setPendingRefundId] = useState<number | null>(null);
  const [refundError, setRefundError] = useState<string | null>(null);
  const queryClient = useQueryClient();

  const paymentsQuery = useQuery({
    queryKey: ['admin', 'payments', { status, page }],
    queryFn: () =>
      listAdminPayments({ status: (status as PaymentStatus) || undefined, page }),
  });

  const refundMutation = useMutation({
    mutationFn: refundPayment,
    onSuccess: () => {
      setPendingRefundId(null);
      setRefundError(null);
      return queryClient.invalidateQueries({ queryKey: ['admin', 'payments'] });
    },
    onError: (error) => {
      setRefundError(
        error instanceof ApiError
          ? error.message
          : 'Le remboursement a échoué. Vérifie le tableau de bord Stripe avant de réessayer.',
      );
    },
  });

  const payments = paymentsQuery.data?.data ?? [];
  const lastPage = paymentsQuery.data?.last_page ?? 1;

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

      {paymentsQuery.isLoading ? (
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      ) : paymentsQuery.isError ? (
        <p role="alert" className="font-inter text-sm text-destructive">
          Impossible de charger les paiements pour le moment, réessaie plus tard.
        </p>
      ) : payments.length === 0 ? (
        <p className="font-inter text-sm text-muted-foreground">Aucun paiement.</p>
      ) : (
        <div className="flex flex-col gap-3">
          {payments.map((payment) => (
            <div
              key={payment.id}
              className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border p-4"
            >
              <div>
                <p className="font-poppins font-medium">
                  {payment.job_offer?.title ?? 'Offre supprimée'}
                </p>
                <p className="text-xs text-muted-foreground">
                  {payment.user.email} —{' '}
                  {new Date(payment.created_at).toLocaleDateString('fr-FR')}
                </p>
              </div>
              <div className="flex flex-wrap items-center gap-2">
                <span className="font-inter text-sm">
                  {(payment.amount_cents / 100).toFixed(2)} {payment.currency}
                </span>
                <Badge variant={STATUS_VARIANT[payment.status] ?? 'outline'}>
                  {payment.status}
                </Badge>

                {/* Seul un paiement encaisse est remboursable — meme regle
                    que PaymentService::refund cote serveur. Le bouton est
                    masque ailleurs plutot que desactive : proposer une
                    action impossible sur un mouvement d'argent invite a
                    cliquer pour voir. */}
                {payment.status === PaymentStatus.SUCCEEDED &&
                  (pendingRefundId === payment.id ? (
                    <span className="flex items-center gap-2">
                      <span className="font-inter text-xs text-muted-foreground">
                        Rembourser {(payment.amount_cents / 100).toFixed(2)}{' '}
                        {payment.currency} et retirer l'offre ?
                      </span>
                      <Button
                        size="sm"
                        variant="destructive"
                        disabled={refundMutation.isPending}
                        onClick={() => refundMutation.mutate(payment.id)}
                      >
                        {refundMutation.isPending ? 'Remboursement…' : 'Confirmer'}
                      </Button>
                      <Button
                        size="sm"
                        variant="outline"
                        disabled={refundMutation.isPending}
                        onClick={() => {
                          setPendingRefundId(null);
                          setRefundError(null);
                        }}
                      >
                        Annuler
                      </Button>
                    </span>
                  ) : (
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => {
                        setPendingRefundId(payment.id);
                        setRefundError(null);
                      }}
                    >
                      Rembourser
                    </Button>
                  ))}
              </div>

              {refundError && pendingRefundId === payment.id && (
                <p role="alert" className="w-full font-inter text-sm text-destructive">
                  {refundError}
                </p>
              )}
            </div>
          ))}
        </div>
      )}

      <AdminPager page={page} lastPage={lastPage} onChange={setPage} />
    </div>
  );
}
