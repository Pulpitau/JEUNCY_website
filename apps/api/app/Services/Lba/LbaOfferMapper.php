<?php

namespace App\Services\Lba;

use App\Enums\WorkMode;
use App\Models\ExternalJobOffer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Traduit une offre telle que La bonne alternance la livre (structure de la
 * route /job/v1/search, identique dans l'export) en ligne de
 * external_job_offers. Pure : aucune base, aucun reseau — testable sur des
 * tableaux.
 *
 * Renvoie null quand l'offre est inexploitable : sans titre, sans lien de
 * candidature, ou sans code postal (donc sans departement, donc hors de
 * tout perimetre).
 */
class LbaOfferMapper
{
    public const DESCRIPTION_MAX = 8000;

    public function map(array $job): ?array
    {
        $identifier = $job['identifier'] ?? [];
        $workplace = $job['workplace'] ?? [];
        $location = $workplace['location'] ?? [];
        $domain = $workplace['domain'] ?? [];
        $apply = $job['apply'] ?? [];
        $contract = $job['contract'] ?? [];
        $offer = $job['offer'] ?? [];
        $publication = $offer['publication'] ?? [];

        $title = trim((string) ($offer['title'] ?? ''));
        $applyUrl = trim((string) ($apply['url'] ?? ''));
        $partnerLabel = (string) ($identifier['partner_label'] ?? '');
        $partnerJobId = (string) ($identifier['partner_job_id'] ?? '');

        if ($title === '' || $applyUrl === '' || ! str_starts_with($applyUrl, 'http')) {
            return null;
        }

        $address = trim((string) ($location['address'] ?? ''));
        [$postalCode, $city] = self::postalCodeAndCity($address);
        if ($postalCode === null) {
            return null;
        }

        $key = $partnerLabel !== '' && $partnerJobId !== ''
            ? $partnerLabel.'|'.$partnerJobId
            : (string) ($identifier['id'] ?? '');
        if ($key === '') {
            return null;
        }

        $coordinates = $location['geopoint']['coordinates'] ?? null;

        return [
            'source' => ExternalJobOffer::SOURCE_LBA,
            'external_key' => Str::limit(hash('sha256', $key), 64, ''),
            'partner_label' => Str::limit($partnerLabel, 120, ''),
            'partner_job_id' => Str::limit($partnerJobId, 120, ''),
            'title' => Str::limit($title, 255, ''),
            'description' => self::cleanText((string) ($offer['description'] ?? '')),
            'company_name' => self::nullable(Str::limit((string) ($workplace['name'] ?? $workplace['brand'] ?? $workplace['legal_name'] ?? ''), 255, '')),
            'company_siret' => self::siret($workplace['siret'] ?? null),
            'company_naf' => self::nullable(Str::limit((string) ($domain['naf']['code'] ?? ''), 10, '')),
            'company_naf_label' => self::nullable(Str::limit((string) ($domain['naf']['label'] ?? ''), 255, '')),
            'company_size' => self::nullable(Str::limit((string) ($workplace['size'] ?? ''), 60, '')),
            'company_website' => self::nullable(Str::limit((string) ($workplace['website'] ?? ''), 255, '')),
            'address' => self::nullable(Str::limit($address, 255, '')),
            'postal_code' => $postalCode,
            'city' => self::nullable(Str::limit($city, 255, '')),
            'department' => self::departmentFromPostalCode($postalCode),
            // GeoJSON : [longitude, latitude].
            'longitude' => is_array($coordinates) && isset($coordinates[0]) ? (float) $coordinates[0] : null,
            'latitude' => is_array($coordinates) && isset($coordinates[1]) ? (float) $coordinates[1] : null,
            'work_mode' => self::workMode($contract['remote'] ?? null)?->value,
            'contract_start' => self::date($contract['start'] ?? null),
            'contract_duration_months' => isset($contract['duration']) ? (int) $contract['duration'] : null,
            'target_diploma_level' => self::nullable((string) ($offer['target_diploma']['european'] ?? '')),
            'target_diploma_label' => self::nullable(Str::limit((string) ($offer['target_diploma']['label'] ?? ''), 255, '')),
            'rome_codes' => array_values(array_filter((array) ($offer['rome_codes'] ?? []), 'is_string')),
            'opening_count' => isset($offer['opening_count']) ? (int) $offer['opening_count'] : null,
            'apply_url' => Str::limit($applyUrl, 1000, ''),
            'is_delegated' => (bool) ($job['is_delegated'] ?? false),
            'published_at' => self::date($publication['creation'] ?? null),
            'expires_at' => self::date($publication['expiration'] ?? null),
            'offer_status' => (string) ($offer['status'] ?? 'Active'),
        ];
    }

    /** @return array{0: ?string, 1: string} */
    public static function postalCodeAndCity(string $address): array
    {
        if (! preg_match('/\b(\d{5})\b\s*(.*)$/u', $address, $m)) {
            return [null, ''];
        }
        // Ce qui suit le code postal est la ville, parfois suivie du pays.
        $city = trim(preg_replace('/,?\s*france\s*$/iu', '', $m[2]) ?? $m[2], " ,\t");

        return [$m[1], Str::title(mb_strtolower($city))];
    }

    public static function departmentFromPostalCode(string $postalCode): string
    {
        if (str_starts_with($postalCode, '97') || str_starts_with($postalCode, '98')) {
            return substr($postalCode, 0, 3);
        }
        if (str_starts_with($postalCode, '20')) {
            return (int) $postalCode < 20200 ? '2A' : '2B';
        }

        return substr($postalCode, 0, 2);
    }

    private static function workMode(?string $remote): ?WorkMode
    {
        return match ($remote) {
            'onsite' => WorkMode::PRESENTIEL,
            'hybrid' => WorkMode::HYBRIDE,
            'remote' => WorkMode::DISTANCIEL,
            default => null,
        };
    }

    private static function date(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function siret(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        return strlen($digits) === 14 ? $digits : null;
    }

    // Les descriptions arrivent parfois en HTML : on garde le texte, les
    // sauts de ligne, rien d'autre. Bornee pour ne pas gonfler la table.
    private static function cleanText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>|<\/p>|<\/li>|<\/div>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return Str::limit(trim($text), self::DESCRIPTION_MAX, '…');
    }

    private static function nullable(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }
}
