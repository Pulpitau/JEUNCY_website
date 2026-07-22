import { useQuery } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { fetchAdminStats } from '@/lib/api/admin';

function StatCard({ label, value }: { label: string; value: string | number }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="font-inter text-sm font-medium text-muted-foreground">
          {label}
        </CardTitle>
      </CardHeader>
      <CardContent>
        <p className="font-poppins text-2xl font-bold">{value}</p>
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
      <StatCard
        label="Revenus"
        value={`${(stats.payments.revenue_cents / 100).toFixed(2)} €`}
      />
      <StatCard label="Salles de visio" value={stats.video_rooms.total} />
      <StatCard label="Visios en cours" value={stats.video_rooms.live} />
    </div>
  );
}
