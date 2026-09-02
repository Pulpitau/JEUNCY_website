<?php

namespace App\Console\Commands;

use App\Models\CvDownload;
use Illuminate\Console\Command;

// Purge du journal des telechargements de CV passe le delai de conservation.
//
// Ce n'est pas une simple hygiene de base : la politique de confidentialite
// (section "4 ter") annonce aux candidats une conservation de trois ans. Sans
// cette commande planifiee, cette annonce serait fausse — le principe de
// limitation de conservation (RGPD art. 5.1.e) impose que la duree declaree
// soit effectivement appliquee, pas seulement affichee.
//
// Toute modification du delai ici doit etre repercutee dans
// apps/web/src/pages/PrivacyPolicy.tsx, et reciproquement.
class PurgeCvDownloads extends Command
{
    protected $signature = 'cv-downloads:purge';

    protected $description = 'Supprime les entrées du journal de téléchargement de CV vieilles de plus de 3 ans';

    public const RETENTION_YEARS = 3;

    public function handle(): int
    {
        $deleted = CvDownload::where('downloaded_at', '<', now()->subYears(self::RETENTION_YEARS))
            ->delete();

        $this->info($deleted.' entrée(s) de journal supprimée(s).');

        return self::SUCCESS;
    }
}
