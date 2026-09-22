<?php

namespace App\Console\Commands;

use App\Models\CandidateProfile;
use App\Models\CfaOrganization;
use App\Models\Company;
use App\Models\JobOffer;
use App\Services\GeocodingService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Rattrape les lignes sans coordonnees : celles ecrites avant le modele
 * match (115 profils, 1 offre, 2 organisations en production au 2026-09-22)
 * et celles dont le geocodage a echoue parce que l'IGN ne repondait pas ce
 * jour-la.
 *
 * Idempotente : ne regarde que les lignes AVEC code postal et SANS
 * coordonnees. Relancee dix fois, elle ne refait que ce qui manque encore —
 * et le cache geocode_cache fait qu'une commune n'est demandee qu'une fois,
 * quel que soit le nombre de lignes qui la citent.
 */
class GeocodeBackfill extends Command
{
    protected $signature = 'geocode:backfill {--chunk=100} {--only= : profiles, offers ou organizations}';

    protected $description = 'Geocode les profils, offres et organisations qui ont un code postal mais pas de coordonnees';

    public function __construct(private readonly GeocodingService $geocodingService)
    {
        parent::__construct();
    }

    public const KINDS = ['profiles', 'offers', 'organizations'];

    public function handle(): int
    {
        $only = $this->option('only');
        $chunk = max(1, (int) $this->option('chunk'));

        // Une valeur inconnue (« --only=profile » au singulier, la faute de
        // frappe la plus probable) ne doit pas afficher « 0 ligne(s) » et
        // sortir en succes : l'operateur croirait le rattrapage fait.
        if ($only !== null && ! in_array($only, self::KINDS, true)) {
            $this->error("--only n'accepte que : ".implode(', ', self::KINDS).'.');

            return self::FAILURE;
        }

        $total = 0;

        if ($only === null || $only === 'profiles') {
            $total += $this->backfill(CandidateProfile::query(), $chunk, 'profils');
        }
        if ($only === null || $only === 'offers') {
            $total += $this->backfill(JobOffer::query(), $chunk, 'offres');
        }
        if ($only === null || $only === 'organizations') {
            $total += $this->backfill(Company::query(), $chunk, 'entreprises');
            $total += $this->backfill(CfaOrganization::query(), $chunk, 'CFA');
        }

        $this->info("Geocodage termine : {$total} ligne(s) traitee(s).");

        return self::SUCCESS;
    }

    private function backfill(Builder $query, int $chunk, string $label): int
    {
        $traitees = 0;

        $query->whereNotNull('postal_code')
            ->whereNull('latitude')
            ->chunkById($chunk, function ($lignes) use (&$traitees) {
                foreach ($lignes as $ligne) {
                    /** @var Model $ligne */
                    $this->geocodingService->apply($ligne, $ligne->postal_code, $ligne->city);
                    $traitees++;
                }
            });

        $this->line("{$label} : {$traitees}");

        return $traitees;
    }
}
