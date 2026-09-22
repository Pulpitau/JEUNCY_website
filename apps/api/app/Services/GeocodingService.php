<?php

namespace App\Services;

use App\Models\CandidateProfile;
use App\Models\GeocodeCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Transforme un couple commune + code postal en coordonnees, via le
 * geocodeur public de la Geoplateforme (IGN), avec cache en base.
 *
 * Trois regles, dans cet ordre d'importance :
 *
 *  1. Une panne du geocodeur ne doit JAMAIS remonter a l'utilisateur. Un
 *     candidat qui enregistre son profil, une entreprise qui publie une
 *     offre : leur action aboutit meme si l'IGN ne repond pas. La ligne
 *     reste simplement sans coordonnees, et geocode:backfill la rattrape.
 *     Meme modele que TrainingOrganizationDetector::nafFor.
 *  2. Jamais la commune seule : « Saint-Martin » existe des dizaines de
 *     fois. Sans code postal a cinq chiffres, on ne geocode pas (MOBILE.md
 *     §6).
 *  3. Le cache porte sur la COMMUNE (code postal + nom normalise), pas sur
 *     la ligne qui l'a demande : une commune est geocodee une fois pour
 *     tous les profils, offres et organisations qui la citent. Un echec est
 *     cache aussi (coordonnees nulles), pour ne pas marteler le service a
 *     chaque enregistrement ; il est reessaye apres GeocodeCache::RETRY_AFTER_DAYS.
 */
class GeocodingService
{
    public const ENDPOINT = 'https://data.geopf.fr/geocodage/search';

    public const TIMEOUT_SECONDS = 4;

    // Precision de stockage. Un profil candidat est arrondi a ~1 km : sa
    // position sert a calculer une distance, jamais a le localiser (decision
    // 2 de MOBILE.md §2 et consentement GPS de §6). Une offre ou une
    // organisation est une adresse professionnelle publique, stockee telle
    // que le geocodeur la rend.
    public const CANDIDATE_PRECISION = 2;

    public const DEFAULT_PRECISION = 6;

    // Issue d'une resolution. FOUND et NOT_FOUND sont des REPONSES du
    // geocodeur (on sait), UNAVAILABLE est une absence de reponse (on ne sait
    // pas). Les confondre revient a traiter une panne de l'IGN comme une
    // commune inexistante — c'est-a-dire a effacer des coordonnees justes.
    public const FOUND = 'FOUND';

    public const NOT_FOUND = 'NOT_FOUND';

    public const UNAVAILABLE = 'UNAVAILABLE';

    /**
     * Resolutions deja faites pendant CETTE requete, panne comprise. Le cache
     * en base ne retient pas les pannes (voir resolve) : sans ce second
     * niveau, une offre express rappellerait un geocodeur muet trois fois de
     * suite (creation, publication, repli), soit douze secondes d'attente
     * pour l'entreprise. Meme mecanique que TrainingOrganizationDetector.
     *
     * @var array<string, array{0: array{lat: float, lng: float}|null, 1: string}>
     */
    private array $resolus = [];

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function geocode(?string $postalCode, ?string $city): ?array
    {
        return $this->resolve($postalCode, $city)[0];
    }

    /**
     * Resolution complete : les coordonnees ET la raison de leur absence.
     *
     * @return array{0: array{lat: float, lng: float}|null, 1: string}
     */
    public function resolve(?string $postalCode, ?string $city): array
    {
        $postalCode = self::normalizePostalCode($postalCode);
        if ($postalCode === null) {
            // Pas de code postal exploitable : la position est inconnue, et
            // on le sait. Ce n'est pas une panne.
            return [null, self::NOT_FOUND];
        }

        $cityNormalized = self::normalizeCity($city);
        $memo = $postalCode.'|'.$cityNormalized;

        if (array_key_exists($memo, $this->resolus)) {
            return $this->resolus[$memo];
        }

        $cached = GeocodeCache::query()
            ->where('postal_code', $postalCode)
            ->where('city_normalized', $cityNormalized)
            ->first();

        if ($cached && ! $cached->isStale()) {
            return $this->resolus[$memo] = $cached->hasCoordinates()
                ? [['lat' => (float) $cached->latitude, 'lng' => (float) $cached->longitude], self::FOUND]
                : [null, self::NOT_FOUND];
        }

        [$coordinates, $outcome] = $this->askGeocoder($postalCode, $cityNormalized === '' ? null : $city);

        // Une panne n'est JAMAIS mise en cache : la ligne negative vaut sept
        // jours (GeocodeCache::RETRY_AFTER_DAYS), et cacher un timeout
        // condamnerait geocode:backfill a ne rien rattraper de la semaine —
        // exactement les lignes qu'il existe pour reparer.
        if ($outcome !== self::UNAVAILABLE) {
            GeocodeCache::updateOrCreate(
                ['postal_code' => $postalCode, 'city_normalized' => $cityNormalized],
                [
                    'latitude' => $coordinates['lat'] ?? null,
                    'longitude' => $coordinates['lng'] ?? null,
                    'resolved_at' => now(),
                ],
            );
        }

        return $this->resolus[$memo] = [$coordinates, $outcome];
    }

    /**
     * Ecrit les coordonnees sur un modele (profil, offre, organisation).
     *
     * Affectation directe + saveQuietly : ces colonnes sont volontairement
     * hors fillable (aucune requete cliente ne doit les poser), et une
     * sauvegarde silencieuse evite de declencher les effets de bord d'un
     * update() metier au milieu d'un enregistrement deja fait.
     *
     * Les coordonnees sont effacees quand la resolution echoue, y compris
     * sur une panne : cette methode n'est appelee qu'a la creation d'une
     * ligne ou quand sa commune VIENT DE CHANGER, donc les anciennes
     * coordonnees ne designent de toute facon plus le bon endroit. Mieux
     * vaut une ligne absente des piles qu'une ligne montree au mauvais
     * departement. Ce qui compte, c'est que la panne soit rattrapable : elle
     * n'est jamais mise en cache (voir resolve), donc geocode:backfill
     * repare la ligne des sa passe suivante.
     */
    public function apply(
        Model $target,
        ?string $postalCode,
        ?string $city,
        string $latColumn = 'latitude',
        string $lngColumn = 'longitude',
    ): void {
        $coordinates = $this->resolve($postalCode, $city)[0];

        $precision = $target instanceof CandidateProfile ? self::CANDIDATE_PRECISION : self::DEFAULT_PRECISION;

        $target->{$latColumn} = $coordinates === null ? null : round($coordinates['lat'], $precision);
        $target->{$lngColumn} = $coordinates === null ? null : round($coordinates['lng'], $precision);
        $target->saveQuietly();
    }

    public static function normalizePostalCode(?string $postalCode): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $postalCode) ?? '';

        return strlen($digits) === 5 ? $digits : null;
    }

    public static function normalizeCity(?string $city): string
    {
        return trim(mb_strtolower(Str::ascii((string) $city)));
    }

    /**
     * @return array{0: array{lat: float, lng: float}|null, 1: string}
     */
    private function askGeocoder(string $postalCode, ?string $city): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->acceptJson()
                ->get(self::ENDPOINT, [
                    'q' => $city ?: $postalCode,
                    'postcode' => $postalCode,
                    'limit' => 1,
                ]);

            if (! $response->successful()) {
                Log::warning("Geocodeur indisponible pour {$postalCode} ({$response->status()}).");

                return [null, self::UNAVAILABLE];
            }

            // GeoJSON : coordinates = [longitude, latitude], dans cet ordre.
            $coordinates = $response->json('features.0.geometry.coordinates');
            if (! is_array($coordinates) || count($coordinates) < 2) {
                // Le geocodeur a repondu, il ne connait pas cette commune.
                return [null, self::NOT_FOUND];
            }

            return [['lat' => (float) $coordinates[1], 'lng' => (float) $coordinates[0]], self::FOUND];
        } catch (\Throwable $e) {
            Log::warning("Geocodage impossible pour {$postalCode} : {$e->getMessage()}");

            return [null, self::UNAVAILABLE];
        }
    }
}
