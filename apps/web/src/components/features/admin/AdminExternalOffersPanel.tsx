import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { ApiError } from '@/lib/api/client';
import {
  blockExternalEmployer,
  getExternalOffersStats,
  listExternalEmployerBlocks,
  listExternalOffersAsAdmin,
  removeExternalEmployerBlock,
  type ExternalImportReport,
} from '@/lib/api/external-job-offers';
import { AdminPager } from './AdminPager';

const STATUS_OPTIONS = [
  { value: '', label: 'Toutes' },
  { value: 'ACTIVE', label: 'Visibles' },
  { value: 'EXCLUDED', label: 'Exclues par le filtre' },
] as const;

const STATS_KEY = ['admin', 'external-offers', 'stats'];
const LIST_KEY = ['admin', 'external-offers', 'list'];
const BLOCKS_KEY = ['admin', 'external-offers', 'blocks'];

function ImportReport({ report }: { report: ExternalImportReport }) {
  const size = report.octets ? `${(report.octets / 1048576).toFixed(0)} Mo` : '—';

  return (
    <div className="rounded-md border border-border p-4">
      <p className="font-poppins text-sm font-semibold text-foreground">
        Dernier import : {new Date(report.date).toLocaleString('fr-FR')}
        {report.mesure_seulement && <Badge className="ml-2">mesure seulement</Badge>}
      </p>
      <dl className="mt-2 grid grid-cols-2 gap-x-6 gap-y-1 font-inter text-sm sm:grid-cols-4">
        <dt className="text-muted-foreground">Fichier</dt>
        <dd>
          {size} · {report.duree_s} s
        </dd>
        <dt className="text-muted-foreground">Offres lues</dt>
        <dd>{report.lus.toLocaleString('fr-FR')}</dd>
        <dt className="text-muted-foreground">Dans le périmètre</dt>
        <dd>{report.retenues.toLocaleString('fr-FR')}</dd>
        <dt className="text-muted-foreground">Visibles</dt>
        <dd className="font-semibold text-foreground">
          {report.actives.toLocaleString('fr-FR')}
        </dd>
        <dt className="text-muted-foreground">Exclues</dt>
        <dd>{report.exclues.toLocaleString('fr-FR')}</dd>
        <dt className="text-muted-foreground">Supprimées</dt>
        <dd>{report.supprimees.toLocaleString('fr-FR')}</dd>
        <dt className="text-muted-foreground">Hors périmètre</dt>
        <dd>{report.hors_perimetre.toLocaleString('fr-FR')}</dd>
        <dt className="text-muted-foreground">Inexploitables</dt>
        <dd>{report.inexploitables.toLocaleString('fr-FR')}</dd>
      </dl>
      {Object.keys(report.par_departement).length > 0 && (
        <p className="mt-2 font-inter text-xs text-muted-foreground">
          Par département :{' '}
          {Object.entries(report.par_departement)
            .map(([dept, count]) => `${dept} (${count})`)
            .join(' · ')}
        </p>
      )}
    </div>
  );
}

export function AdminExternalOffersPanel() {
  const queryClient = useQueryClient();
  const [status, setStatus] = useState<'' | 'ACTIVE' | 'EXCLUDED'>('EXCLUDED');
  const [q, setQ] = useState('');
  const [draftQ, setDraftQ] = useState('');
  const [page, setPage] = useState(1);
  const [feedback, setFeedback] = useState<string | null>(null);

  const statsQuery = useQuery({ queryKey: STATS_KEY, queryFn: getExternalOffersStats });
  const listQuery = useQuery({
    queryKey: [...LIST_KEY, { status, q, page }],
    queryFn: () =>
      listExternalOffersAsAdmin({ status: status || undefined, q: q || undefined, page }),
  });
  const blocksQuery = useQuery({
    queryKey: BLOCKS_KEY,
    queryFn: listExternalEmployerBlocks,
  });

  function invalidateAll() {
    return Promise.all([
      queryClient.invalidateQueries({ queryKey: STATS_KEY }),
      queryClient.invalidateQueries({ queryKey: LIST_KEY }),
      queryClient.invalidateQueries({ queryKey: BLOCKS_KEY }),
    ]);
  }

  const blockMutation = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason?: string }) =>
      blockExternalEmployer(id, reason),
    onSuccess: (block) => {
      setFeedback(
        `${block.display_name} bloqué : ${block.offres_retirees} offre${block.offres_retirees > 1 ? 's' : ''} retirée${block.offres_retirees > 1 ? 's' : ''}.`,
      );
      void invalidateAll();
    },
    onError: (error) =>
      setFeedback(error instanceof ApiError ? error.message : 'Blocage impossible.'),
  });
  const unblockMutation = useMutation({
    mutationFn: removeExternalEmployerBlock,
    onSuccess: () => void invalidateAll(),
  });

  const stats = statsQuery.data;
  const offers = listQuery.data?.data ?? [];
  const lastPage = listQuery.data?.last_page ?? 1;
  const blocks = blocksQuery.data ?? [];

  return (
    <div className="flex flex-col gap-6">
      {/* L'essentiel d'abord : ce que le filtre laisse passer, ce qu'il
          ecarte et pourquoi. C'est l'outil qui permet d'ajuster la regle
          sans lire le code. */}
      {stats && (
        <div className="flex flex-col gap-3">
          <div className="grid gap-3 sm:grid-cols-3">
            <div className="rounded-md border border-border p-4">
              <p className="font-inter text-xs uppercase tracking-wide text-muted-foreground">
                Offres visibles
              </p>
              <p className="font-poppins text-3xl font-bold text-foreground">
                {stats.actives.toLocaleString('fr-FR')}
              </p>
            </div>
            <div className="rounded-md border border-border p-4">
              <p className="font-inter text-xs uppercase tracking-wide text-muted-foreground">
                Exclues par le filtre
              </p>
              <p className="font-poppins text-3xl font-bold text-foreground">
                {stats.exclues.toLocaleString('fr-FR')}
              </p>
            </div>
            <div className="rounded-md border border-border p-4">
              <p className="font-inter text-xs uppercase tracking-wide text-muted-foreground">
                Employeurs bloqués à la main
              </p>
              <p className="font-poppins text-3xl font-bold text-foreground">
                {stats.employeurs_bloques}
              </p>
            </div>
          </div>
          <p className="font-inter text-xs text-muted-foreground">
            Périmètre : départements {stats.departements.join(', ')}.
          </p>
          {stats.dernier_import ? (
            <ImportReport report={stats.dernier_import} />
          ) : (
            <p className="rounded-md border border-jeuncy-orange/40 bg-jeuncy-orange/10 px-4 py-3 font-inter text-sm text-foreground">
              Aucun import n'a encore tourné. Il faut une clé API La bonne alternance dans
              la configuration du serveur ; l'import passe ensuite chaque nuit.
            </p>
          )}
          {Object.keys(stats.exclues_par_raison).length > 0 && (
            <div className="rounded-md border border-border p-4">
              <p className="font-poppins text-sm font-semibold text-foreground">
                Pourquoi des offres sont exclues
              </p>
              <ul className="mt-2 flex flex-col gap-1 font-inter text-sm">
                {Object.entries(stats.exclues_par_raison).map(([reason, count]) => (
                  <li key={reason} className="flex justify-between gap-4">
                    <span className="text-muted-foreground">{reason}</span>
                    <span className="font-medium text-foreground">{count}</span>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}

      {feedback && (
        <p role="status" className="font-inter text-sm text-foreground">
          {feedback}
        </p>
      )}

      <form
        className="flex flex-col gap-2 sm:flex-row"
        onSubmit={(event) => {
          event.preventDefault();
          setQ(draftQ);
          setPage(1);
        }}
      >
        <select
          className={cn(
            'flex h-10 w-full max-w-xs rounded-md border border-input bg-background px-3 py-2 text-sm font-inter focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
          )}
          value={status}
          onChange={(event) => {
            setStatus(event.target.value as '' | 'ACTIVE' | 'EXCLUDED');
            setPage(1);
          }}
        >
          {STATUS_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
        <Input
          placeholder="Employeur ou titre"
          value={draftQ}
          onChange={(event) => setDraftQ(event.target.value)}
          className="sm:max-w-xs"
        />
        <Button type="submit" variant="outline">
          Filtrer
        </Button>
      </form>

      {listQuery.isLoading ? (
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      ) : offers.length === 0 ? (
        <p className="font-inter text-sm text-muted-foreground">Aucune offre.</p>
      ) : (
        <ul className="flex flex-col gap-2">
          {offers.map((offer) => (
            <li
              key={offer.id}
              className="flex flex-col gap-2 rounded-md border border-border p-3 sm:flex-row sm:items-start sm:justify-between"
            >
              <div className="min-w-0">
                <p className="font-poppins text-sm font-medium text-foreground">
                  {offer.title}
                </p>
                <p className="font-inter text-xs text-muted-foreground">
                  {offer.company_name ?? 'Employeur non précisé'}
                  {offer.company_siret ? ` · SIRET ${offer.company_siret}` : ''}
                  {offer.company_naf ? ` · NAF ${offer.company_naf}` : ''}
                  {offer.city ? ` · ${offer.city} (${offer.department})` : ''}
                  {offer.partner_label ? ` · ${offer.partner_label}` : ''}
                </p>
                {offer.status === 'EXCLUDED' ? (
                  <Badge variant="destructive" className="mt-1">
                    {offer.exclusion_reason}
                  </Badge>
                ) : (
                  <Badge variant="outline" className="mt-1">
                    Visible
                  </Badge>
                )}
              </div>
              <div className="flex shrink-0 gap-2">
                <a
                  href={offer.apply_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="font-inter text-xs text-primary hover:underline"
                >
                  Voir l'annonce
                </a>
                {offer.status === 'ACTIVE' && (
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={blockMutation.isPending}
                    onClick={() => {
                      if (
                        window.confirm(
                          `Bloquer l'employeur « ${offer.company_name ?? offer.company_siret} » ? Toutes ses offres seront retirées, maintenant et à chaque import.`,
                        )
                      ) {
                        setFeedback(null);
                        blockMutation.mutate({
                          id: offer.id,
                          reason: 'école signalée par un administrateur',
                        });
                      }
                    }}
                  >
                    C'est une école
                  </Button>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}
      <AdminPager page={page} lastPage={lastPage} onChange={setPage} />

      {blocks.length > 0 && (
        <div className="rounded-md border border-border p-4">
          <p className="font-poppins text-sm font-semibold text-foreground">
            Employeurs bloqués à la main
          </p>
          <ul className="mt-2 flex flex-col gap-1">
            {blocks.map((block) => (
              <li
                key={block.id}
                className="flex items-center justify-between gap-4 font-inter text-sm"
              >
                <span>
                  {block.display_name}
                  <span className="text-muted-foreground">
                    {block.siret ? ` · ${block.siret}` : ''}
                    {block.reason ? ` · ${block.reason}` : ''}
                  </span>
                </span>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  disabled={unblockMutation.isPending}
                  onClick={() => unblockMutation.mutate(block.id)}
                >
                  Débloquer
                </Button>
              </li>
            ))}
          </ul>
          <p className="mt-2 font-inter text-xs text-muted-foreground">
            Débloquer ne remet rien en ligne tout de suite : l'import de la nuit suivante
            réévalue les offres de cet employeur avec le filtre normal.
          </p>
        </div>
      )}
    </div>
  );
}
