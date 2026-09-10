import { useQuery } from '@tanstack/react-query';
import { PaymentStatus, PaymentType } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import { listMyPayments } from '@/lib/api/payments';

const STATUS_LABELS: Record<string, string> = {
  [PaymentStatus.PENDING]: 'En attente',
  [PaymentStatus.SUCCEEDED]: 'Réussi',
  [PaymentStatus.FAILED]: 'Échoué',
  [PaymentStatus.REFUNDED]: 'Remboursé',
};

const STATUS_VARIANT: Record<
  string,
  'default' | 'destructive' | 'secondary' | 'outline'
> = {
  [PaymentStatus.SUCCEEDED]: 'default',
  [PaymentStatus.FAILED]: 'destructive',
  [PaymentStatus.REFUNDED]: 'secondary',
  [PaymentStatus.PENDING]: 'outline',
};

// Un prelevement d'abonnement ne se rattache a aucune offre. Sans ce
// libelle, il s'affichait « Offre supprimée » — un intitule alarmant pour
// ce qui est en realite une facture mensuelle normale.
function paymentLabel(payment: {
  type: PaymentType;
  job_offer: { title: string } | null;
}) {
  if (payment.type === PaymentType.SUBSCRIPTION) {
    return 'Abonnement mensuel';
  }
  if (payment.job_offer) {
    return `${payment.job_offer.title} — 1 mois de mise en ligne`;
  }
  return 'Offre supprimée';
}

export function MyPayments() {
  const paymentsQuery = useQuery({
    queryKey: ['payments', 'mine'],
    queryFn: listMyPayments,
  });

  const payments = paymentsQuery.data ?? [];

  return (
    <main className="mx-auto flex max-w-3xl flex-col gap-6 px-4 py-12">
      <div>
        <h1 className="font-poppins text-3xl font-bold">Mes paiements</h1>
        <p className="mt-1 font-inter text-muted-foreground">
          Historique de tes publications d'offres et de tes prélèvements d'abonnement.
        </p>
      </div>

      {paymentsQuery.isLoading ? (
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      ) : paymentsQuery.isError ? (
        <p role="alert" className="font-inter text-sm text-destructive">
          Impossible de charger tes paiements pour le moment, réessaie plus tard.
        </p>
      ) : payments.length === 0 ? (
        <p className="font-inter text-sm text-muted-foreground">
          Aucun paiement pour l'instant.
        </p>
      ) : (
        <div className="flex flex-col gap-3">
          {payments.map((payment) => (
            <div
              key={payment.id}
              className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border p-4"
            >
              <div>
                <p className="font-poppins font-medium">{paymentLabel(payment)}</p>
                <p className="text-xs text-muted-foreground">
                  {new Date(payment.created_at).toLocaleDateString('fr-FR')}
                </p>
              </div>
              <div className="flex items-center gap-2">
                <span className="font-inter text-sm">
                  {(payment.amount_cents / 100).toFixed(2)} {payment.currency}
                </span>
                <Badge variant={STATUS_VARIANT[payment.status] ?? 'outline'}>
                  {STATUS_LABELS[payment.status] ?? payment.status}
                </Badge>
              </div>
            </div>
          ))}
        </div>
      )}
    </main>
  );
}
