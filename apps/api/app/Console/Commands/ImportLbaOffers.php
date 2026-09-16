<?php

namespace App\Console\Commands;

use App\Services\Lba\LbaClient;
use App\Services\Lba\LbaImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Import quotidien des offres de La bonne alternance.
 *
 *   php artisan lba:import                     telecharge l'export et importe
 *   php artisan lba:import --mesure            telecharge et compte, n'ecrit rien
 *   php artisan lba:import --fichier=x.json    lit un fichier deja telecharge
 *
 * Sans cle API configuree, la commande se termine sans rien faire ni rien
 * signaler d'anormal : c'est l'etat normal d'un environnement de dev.
 */
class ImportLbaOffers extends Command
{
    protected $signature = 'lba:import {--fichier= : Fichier JSON deja telecharge, au lieu d\'appeler l\'API} {--mesure : Compter sans rien ecrire}';

    protected $description = 'Importe les offres de La bonne alternance dans le perimetre configure, en ecartant les ecoles';

    public function __construct(
        private readonly LbaClient $client,
        private readonly LbaImportService $importer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $file = $this->option('fichier');
        $downloaded = false;

        if ($file === null) {
            if (! $this->client->configured()) {
                $this->info('LBA_API_KEY absente : import ignore.');

                return self::SUCCESS;
            }
            $this->line('Telechargement de l\'export...');
            $file = $this->client->downloadExport();
            $downloaded = true;
            $this->line('Export recu : '.number_format(filesize($file) / 1048576, 1, ',', ' ').' Mo');
        } elseif (! is_file($file)) {
            $this->error("Fichier introuvable : {$file}");

            return self::FAILURE;
        }

        try {
            $report = $this->importer->importFromFile(
                $file,
                (bool) $this->option('mesure') || (bool) config('services.lba.mesure_seulement'),
                fn (int $n) => $this->line("  {$n} offres lues..."),
            );
        } catch (\Throwable $e) {
            Log::error('Import LBA echoue : '.$e->getMessage());
            $this->error('Import echoue : '.$e->getMessage());

            return self::FAILURE;
        } finally {
            if ($downloaded) {
                @unlink($file);
            }
        }

        $this->line('');
        $this->info(sprintf(
            '%s offres lues, %s dans le perimetre, %s visibles, %s exclues, %s supprimees (%s s).',
            $report['lus'],
            $report['retenues'],
            $report['actives'],
            $report['exclues'],
            $report['supprimees'],
            $report['duree_s'],
        ));
        foreach ($report['exclues_par_raison'] as $reason => $count) {
            $this->line("  - {$count} : {$reason}");
        }
        foreach ($report['par_departement'] as $department => $count) {
            $this->line("  dept {$department} : {$count}");
        }

        return self::SUCCESS;
    }
}
