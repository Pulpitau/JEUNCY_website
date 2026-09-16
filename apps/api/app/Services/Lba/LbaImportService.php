<?php

namespace App\Services\Lba;

use App\Enums\ExternalJobOfferStatus;
use App\Models\ExternalJobOffer;
use App\Support\JsonArrayStreamer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Import complet des offres La bonne alternance dans external_job_offers.
 *
 * Chaque passe lit TOUT l'export, ne garde que les departements du
 * perimetre, passe chaque offre au filtre des ecoles, et ecrit le resultat
 * (offre active, ou exclue avec sa raison). Ce qui n'est plus dans l'export
 * est supprime — mais seulement a la fin d'une lecture complete reussie :
 * un fichier tronque ou une panne en cours de route ne doit jamais vider
 * la table.
 *
 * Le rapport de la derniere passe est conserve en cache (CACHE_KEY) et
 * expose a l'admin et a /deploy/{token}/scheduler : un import qui echoue
 * en silence chaque nuit serait invisible autrement.
 */
class LbaImportService
{
    public const CACHE_KEY = 'lba.dernier_import';

    private const BATCH = 200;

    public function __construct(
        private readonly LbaOfferMapper $mapper,
        private readonly ExternalOfferFilter $filter,
    ) {}

    /**
     * @param  bool  $measureOnly  ne rien ecrire, seulement compter (pour
     *                             juger le filtre sur de vraies donnees
     *                             avant de mettre quoi que ce soit en ligne)
     * @return array le rapport de la passe
     */
    public function importFromFile(string $path, bool $measureOnly = false, ?callable $onProgress = null): array
    {
        $startedAt = now();
        $batchId = (string) Str::uuid();
        $departments = array_fill_keys((array) config('services.lba.departements'), true);
        $this->filter->loadManualBlocks();

        $report = [
            'date' => $startedAt->toDateTimeString(),
            'mesure_seulement' => $measureOnly,
            'octets' => is_file($path) ? filesize($path) : null,
            'lus' => 0,
            'inexploitables' => 0,
            'hors_perimetre' => 0,
            'recruteurs_ignores' => 0,
            'inactives' => 0,
            'retenues' => 0,
            'actives' => 0,
            'exclues' => 0,
            'exclues_par_raison' => [],
            'exclues_exemples' => [],
            'par_departement' => [],
            'supprimees' => 0,
            'duree_s' => 0,
        ];

        $batch = [];
        // L'export est un tableau a la racine, offres et « recruteurs » meles
        // (constate le 2026-09-16 sur le vrai fichier : 576 Mo, indente) — pas
        // l'objet {"jobs": [...]} de la route search.
        foreach (JsonArrayStreamer::objects($path) as $job) {
            $report['lus']++;
            if ($onProgress && $report['lus'] % 5000 === 0) {
                $onProgress($report['lus']);
            }

            // « recruteurs_lba » : des entreprises SANS offre, proposees pour
            // une candidature spontanee. Ce ne sont pas des annonces.
            if (($job['identifier']['partner_label'] ?? '') === 'recruteurs_lba') {
                $report['recruteurs_ignores']++;

                continue;
            }

            $row = $this->mapper->map($job);
            if ($row === null) {
                $report['inexploitables']++;

                continue;
            }
            if (! isset($departments[$row['department']])) {
                $report['hors_perimetre']++;

                continue;
            }
            if ($row['offer_status'] !== 'Active') {
                $report['inactives']++;

                continue;
            }
            unset($row['offer_status']);

            $report['retenues']++;
            $report['par_departement'][$row['department']] = ($report['par_departement'][$row['department']] ?? 0) + 1;

            $reason = $this->filter->exclusionReason([
                'company_name' => $row['company_name'],
                'company_siret' => $row['company_siret'],
                'company_naf' => $row['company_naf'],
                'description' => $row['description'],
                'is_delegated' => $row['is_delegated'],
            ]);

            if ($reason === null) {
                $report['actives']++;
                $row['status'] = ExternalJobOfferStatus::ACTIVE->value;
                $row['exclusion_reason'] = null;
            } else {
                $report['exclues']++;
                $report['exclues_par_raison'][$reason] = ($report['exclues_par_raison'][$reason] ?? 0) + 1;
                if (count($report['exclues_exemples']) < 40) {
                    $report['exclues_exemples'][] = [
                        'employeur' => $row['company_name'],
                        'titre' => $row['title'],
                        'departement' => $row['department'],
                        'raison' => $reason,
                    ];
                }
                $row['status'] = ExternalJobOfferStatus::EXCLUDED->value;
                $row['exclusion_reason'] = mb_substr($reason, 0, 255);
            }

            if ($measureOnly) {
                continue;
            }

            $row['rome_codes'] = json_encode($row['rome_codes']);
            $row['import_batch'] = $batchId;
            $row['last_seen_at'] = $startedAt;
            $row['created_at'] = $startedAt;
            $row['updated_at'] = $startedAt;
            $batch[] = $row;
            if (count($batch) >= self::BATCH) {
                $this->flush($batch);
                $batch = [];
            }
        }

        if (! $measureOnly) {
            $this->flush($batch);
            // Lecture complete : ce que cette passe n'a pas vu n'existe plus.
            $report['supprimees'] = ExternalJobOffer::query()
                ->where('source', ExternalJobOffer::SOURCE_LBA)
                ->where('import_batch', '!=', $batchId)
                ->delete();
        }

        arsort($report['exclues_par_raison']);
        ksort($report['par_departement']);
        $report['duree_s'] = (int) $startedAt->diffInSeconds(now());

        // Le rapport est conserve meme en mesure : c'est precisement ce qu'on
        // veut lire le lendemain d'une passe a blanc.
        Cache::forever(self::CACHE_KEY, $report);
        // Le compteur de la page d'accueil doit refleter la passe aussitot.
        Cache::forget('offres.compteur');

        return $report;
    }

    // Upsert sur (source, external_key) : une offre deja connue est mise a
    // jour (titre, statut, raison...), created_at conserve.
    private function flush(array $batch): void
    {
        if ($batch === []) {
            return;
        }

        $columns = array_keys($batch[0]);
        $update = array_values(array_diff($columns, ['source', 'external_key', 'created_at']));

        DB::transaction(fn () => ExternalJobOffer::query()->upsert($batch, ['source', 'external_key'], $update));
    }

    public static function lastReport(): ?array
    {
        $report = Cache::get(self::CACHE_KEY);

        return is_array($report) ? $report : null;
    }

    public static function lastReportAge(): ?int
    {
        $report = self::lastReport();
        if (! $report || empty($report['date'])) {
            return null;
        }

        return (int) Carbon::parse($report['date'])->diffInHours(now());
    }
}
