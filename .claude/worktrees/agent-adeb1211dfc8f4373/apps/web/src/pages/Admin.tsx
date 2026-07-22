import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { AdminStatsPanel } from '@/components/features/admin/AdminStatsPanel';
import { AdminUsersPanel } from '@/components/features/admin/AdminUsersPanel';
import { AdminJobOffersPanel } from '@/components/features/admin/AdminJobOffersPanel';
import { AdminPaymentsPanel } from '@/components/features/admin/AdminPaymentsPanel';
import { AdminVideoRoomsPanel } from '@/components/features/admin/AdminVideoRoomsPanel';

const TABS = [
  { key: 'stats', label: 'Statistiques' },
  { key: 'users', label: 'Utilisateurs' },
  { key: 'job-offers', label: 'Offres' },
  { key: 'payments', label: 'Paiements' },
  { key: 'video-rooms', label: 'Visios' },
] as const;

type TabKey = (typeof TABS)[number]['key'];

export function Admin() {
  const [tab, setTab] = useState<TabKey>('stats');

  return (
    <main className="mx-auto flex max-w-5xl flex-col gap-6 px-4 py-12">
      <div>
        <h1 className="font-poppins text-3xl font-bold">Administration</h1>
        <p className="mt-1 font-inter text-muted-foreground">
          Statistiques de la plateforme, modération des comptes et des offres, supervision
          des paiements et des visioconférences.
        </p>
      </div>

      <div className="flex flex-wrap gap-2 border-b border-border pb-2">
        {TABS.map((item) => (
          <Button
            key={item.key}
            type="button"
            variant={tab === item.key ? 'gradient' : 'ghost'}
            size="sm"
            className={cn(tab === item.key && 'pointer-events-none')}
            onClick={() => setTab(item.key)}
          >
            {item.label}
          </Button>
        ))}
      </div>

      {tab === 'stats' && <AdminStatsPanel />}
      {tab === 'users' && <AdminUsersPanel />}
      {tab === 'job-offers' && <AdminJobOffersPanel />}
      {tab === 'payments' && <AdminPaymentsPanel />}
      {tab === 'video-rooms' && <AdminVideoRoomsPanel />}
    </main>
  );
}
