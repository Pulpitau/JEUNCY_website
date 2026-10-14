import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  decideVerification,
  handleReport,
  listPendingReports,
  listPendingVerifications,
  type PendingVerification,
} from '@/lib/api/admin';

// Les deux files qui protegent les candidats, dont des mineurs :
// - aucune organisation ne voit un candidat avant qu'un humain l'ait validee
//   (audit B1 : le registre des entreprises ne suffit plus) ;
// - les signalements, traites sous 24 h (/mentions-legales §8).
export function AdminModerationPanel() {
  return (
    <div className="flex flex-col gap-8">
      <VerificationsSection />
      <ReportsSection />
    </div>
  );
}

function VerificationsSection() {
  const query = useQuery({
    queryKey: ['admin', 'verifications'],
    queryFn: listPendingVerifications,
  });
  const items = query.data ?? [];

  return (
    <section className="flex flex-col gap-3">
      <h2 className="font-poppins text-xl font-semibold">Vérifications en attente</h2>
      <p className="font-inter text-sm text-muted-foreground">
        Tant qu&apos;elle n&apos;est pas validée ici, une organisation ne voit aucun
        candidat.
      </p>
      {query.isLoading ? (
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      ) : query.isError ? (
        <p role="alert" className="font-inter text-sm text-destructive">
          Impossible de charger les vérifications, réessaie plus tard.
        </p>
      ) : items.length === 0 ? (
        <p className="font-inter text-sm text-muted-foreground">
          Aucune organisation en attente.
        </p>
      ) : (
        items.map((item) => (
          <VerificationRow key={`${item.type}-${item.id}`} item={item} />
        ))
      )}
    </section>
  );
}

function VerificationRow({ item }: { item: PendingVerification }) {
  const queryClient = useQueryClient();
  const [note, setNote] = useState('');

  // La note est exigee par le serveur, meme pour accorder : six mois plus
  // tard, une validation sans raison ecrite ne se distingue pas d'une erreur.
  const mutation = useMutation({
    mutationFn: (status: 'VERIFIED' | 'REJECTED') =>
      decideVerification(item, { status, note: note.trim() }),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: ['admin', 'verifications'] }),
  });
  const noteOk = note.trim().length >= 3;

  return (
    <div className="flex flex-col gap-3 rounded-md border border-border p-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <p className="font-poppins font-medium">{item.name}</p>
          <p className="text-xs text-muted-foreground">
            {item.email} · SIRET {item.siret ?? '—'} · inscrite le{' '}
            {new Date(item.created_at).toLocaleDateString('fr-FR')}
          </p>
        </div>
        <Badge variant="outline">{item.type === 'CFA' ? 'CFA' : 'Entreprise'}</Badge>
      </div>
      {item.verification_note && (
        <p className="font-inter text-sm text-muted-foreground">
          {item.verification_note}
        </p>
      )}
      <Input
        value={note}
        onChange={(event) => setNote(event.target.value)}
        placeholder="Note obligatoire (ex. : appel au gérant le 14/10, site web vérifié)"
        aria-label={`Note de vérification pour ${item.name}`}
        maxLength={255}
      />
      <div className="flex flex-wrap gap-2">
        <Button
          type="button"
          size="sm"
          disabled={!noteOk || mutation.isPending}
          onClick={() => mutation.mutate('VERIFIED')}
        >
          Valider
        </Button>
        <Button
          type="button"
          size="sm"
          variant="ghost"
          disabled={!noteOk || mutation.isPending}
          onClick={() => mutation.mutate('REJECTED')}
        >
          Refuser
        </Button>
      </div>
      {mutation.isError && (
        <p role="alert" className="font-inter text-sm text-destructive">
          {mutation.error.message}
        </p>
      )}
    </div>
  );
}

const REPORT_REASON_LABELS: Record<string, string> = {
  'propos-deplaces': 'Propos déplacés ou insultants',
  'contenu-choquant': 'Contenu choquant ou violent',
  'fausse-annonce': 'Fausse annonce ou arnaque',
  'demande-argent': "Demande d'argent",
  coordonnees: 'Demande de coordonnées personnelles',
  autre: 'Autre',
};

function ReportsSection() {
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ['admin', 'reports'], queryFn: listPendingReports });
  const mutation = useMutation({
    mutationFn: handleReport,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'reports'] }),
  });
  const reports = query.data?.data ?? [];

  return (
    <section className="flex flex-col gap-3">
      <h2 className="font-poppins text-xl font-semibold">Signalements en attente</h2>
      <p className="font-inter text-sm text-muted-foreground">
        Les plus anciens en tête. Engagement public : traitement sous 24 h.
      </p>
      {query.isLoading ? (
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      ) : query.isError ? (
        <p role="alert" className="font-inter text-sm text-destructive">
          Impossible de charger les signalements, réessaie plus tard.
        </p>
      ) : reports.length === 0 ? (
        <p className="font-inter text-sm text-muted-foreground">
          Aucun signalement en attente.
        </p>
      ) : (
        reports.map((report) => (
          <div
            key={report.id}
            className="flex flex-col gap-2 rounded-md border border-border p-4"
          >
            <div className="flex flex-wrap items-center justify-between gap-2">
              <p className="font-poppins font-medium">
                {REPORT_REASON_LABELS[report.reason] ?? report.reason}
              </p>
              <span className="text-xs text-muted-foreground">
                {new Date(report.created_at).toLocaleString('fr-FR')}
              </span>
            </div>
            <p className="text-xs text-muted-foreground">
              Par {report.reporter?.email ?? 'compte supprimé'} · visé :{' '}
              {report.reported?.email ?? report.job_offer?.title ?? '—'}
            </p>
            {report.details && <p className="font-inter text-sm">{report.details}</p>}
            <div>
              <Button
                type="button"
                size="sm"
                variant="ghost"
                disabled={mutation.isPending}
                onClick={() => mutation.mutate(report.id)}
              >
                Marquer comme traité
              </Button>
            </div>
          </div>
        ))
      )}
    </section>
  );
}
