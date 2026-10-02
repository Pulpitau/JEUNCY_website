<?php

namespace App\Support;

use App\Enums\ExternalJobOfferStatus;
use App\Enums\JobOfferStatus;
use App\Models\ExternalJobOffer;
use App\Models\JobOffer;
use Illuminate\Support\Facades\Cache;

/**
 * Combien d'offres sont en ligne, a l'instant.
 *
 * EXTRAIT DE PublicJobOfferController::count() le 2026-10-01 parce que la
 * lettre hebdomadaire annonce ce meme chiffre. Deux requetes separees auraient
 * fini par diverger, et le jour ou elles divergent, le site et l'email que le
 * candidat vient de recevoir se contredisent sous ses yeux.
 *
 * Le cache de dix minutes vient de l'usage d'origine : le compteur est affiche
 * sur la page d'accueil et sur /offres, donc sur presque chaque visite. Il est
 * vide a chaque import La bonne alternance (LbaImportService), ou la cle est
 * ecrite en dur — volontairement, comme CLE_BATTEMENT dans bootstrap/app.php :
 * un fichier absent ne doit jamais pouvoir faire tomber l'import de nuit. Les
 * deux valeurs doivent rester identiques.
 */
class OffersCount
{
    public const CLE_CACHE = 'offres.compteur';

    public const DUREE_CACHE_S = 600;

    /**
     * @return array{jeuncy: int, partenaires: int, total: int}
     */
    public static function current(): array
    {
        return Cache::remember(self::CLE_CACHE, self::DUREE_CACHE_S, function () {
            $jeuncy = JobOffer::query()->where('status', JobOfferStatus::PUBLISHED)->count();
            $partenaires = ExternalJobOffer::query()->where('status', ExternalJobOfferStatus::ACTIVE)->count();

            return ['jeuncy' => $jeuncy, 'partenaires' => $partenaires, 'total' => $jeuncy + $partenaires];
        });
    }
}
