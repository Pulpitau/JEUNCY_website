<?php

namespace App\Console\Commands;

use App\Services\MatchReminderService;
use Illuminate\Console\Command;

/**
 * Relances du modele match (MOBILE.md §5, lot 4).
 *
 * La commande ne decide de rien : elle appelle le service et journalise.
 * Toute la cascade — qui est relance, quand, et ce qui se ferme — vit dans
 * MatchReminderService, ou elle est testable sans passer par Artisan.
 *
 * Planifiee une fois par jour via le montage `unePasseParPeriode` de
 * bootstrap/app.php : le cron d'OVH est horaire et saute des passages, c'est
 * un marqueur en cache qui garantit une passe quotidienne. La commande est
 * de toute facon idempotente (chaque ligne porte l'etage atteint), donc un
 * passage en trop ne renvoie rien.
 */
class RemindMatches extends Command
{
    protected $signature = 'matches:remind {--a-blanc : Compte ce qui partirait, sans rien envoyer ni ecrire}';

    protected $description = 'Relance les intérêts, matchs et candidatures restés sans réponse';

    public function handle(MatchReminderService $service): int
    {
        $aBlanc = (bool) $this->option('a-blanc');
        $compte = $service->run($aBlanc);

        if ($aBlanc) {
            $this->warn("Passe À BLANC : rien n'a été envoyé ni enregistré.");
        }

        if ($compte === []) {
            $this->info('Aucune relance à envoyer.');

            return self::SUCCESS;
        }

        foreach ($compte as $etage => $nombre) {
            $this->line("{$etage} : {$nombre}");
        }

        $this->info('Relances envoyées : '.array_sum($compte));

        return self::SUCCESS;
    }
}
