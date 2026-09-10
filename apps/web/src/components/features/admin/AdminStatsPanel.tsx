import { useQuery } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { fetchAdminStats } from '@/lib/api/admin';

// Un tiret plutot qu'un zero quand l'API ne fournit pas encore le chiffre :
// afficher « 0,00 € » de chiffre d'affaires serait une information fausse.
function euros(cents: number | undefined) {
  return cents === undefined ? '—' : `${(cents / 100).toFixed(2)} €`;
}

function StatCard({
  label,
  value,
}: {
  label: string;
  value: string | number | undefined;
}) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="font-inter text-sm font-medium text-muted-foreground">
          {label}
        </CardTitle>
      </CardHeader>
      <CardContent>
        <p className="font-poppins text-2xl font-bold">{value ?? '—'}</p>
      </CardContent>
    </Card>
  );
}

export function AdminStatsPanel() {
  const statsQuery = useQuery({ queryKey: ['admin', 'stats'], queryFn: fetchAdminStats });

  if (statsQuery.isLoading) {
    return <p className="font-inter text-sm text-muted-foreground">Chargement…</p>;
  }

  if (statsQuery.isError) {
    return (
      <p role="alert" className="font-inter text-sm text-destructive">
        Impossible de charger les statistiques pour le moment, réessaie plus tard.
      </p>
    );
  }

  const stats = statsQuery.data;
  if (!stats) return null;

  return (
    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
      <StatCard label="Utilisateurs" value={stats.users.total} />
      <StatCard label="Candidats" value={stats.users.candidates} />
      <StatCard label="Entreprises" value={stats.users.companies} />
      <StatCard label="CFA" value={stats.users.cfa_organizations} />
      <StatCard label="Comptes suspendus" value={stats.users.suspended} />
      <StatCard label="Offres publiées" value={stats.job_offers.published} />
      <StatCard label="Offres au total" value={stats.job_offers.total} />
      <StatCard label="Candidatures" value={stats.applications.total} />
      <StatCard label="Paiements réussis" value={stats.payments.succeeded_count} />
      <StatCard label="Revenus (total)" value={euros(stats.payments.revenue_cents)} />
      {/* Ponctuel et recurrent ne se pilotent pas pareil : un total
          unique masquait celui des deux qui pese le plus. */}
      <StatCard
        label="dont annonces"
        value={euros(stats.payments.offers_revenue_cents)}
      />
      <StatCard
        label="dont abonnements"
        value={euros(stats.payments.subscriptions_revenue_cents)}
      />
      {/* Ce qui rentrera le mois prochain sans qu'aucune vente n'ait
          lieu — calcule sur les abonnements actifs uniquement. */}
      <StatCard
        label="Revenu mensuel récurrent"
        value={euros(stats.subscriptions?.mrr_cents)}
      />
      <StatCard label="Abonnements actifs" value={stats.subscriptions?.active} />
      <StatCard label="Abonnements impayés" value={stats.subscriptions?.past_due} />
      <StatCard label="Abonnements résiliés" value={stats.subscriptions?.canceled} />
      <StatCard
        label="Places fondateur prises"
        value={
          stats.subscriptions
            ? `${stats.subscriptions.founder_seats_taken} / 50`
            : undefined
        }
      />
      <StatCard label="Salles de visio" value={stats.video_rooms.total} />
      <StatCard label="Visios en cours" value={stats.video_rooms.live} />
    </div>
  );
}
