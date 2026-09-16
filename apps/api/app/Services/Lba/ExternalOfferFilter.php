<?php

namespace App\Services\Lba;

use App\Models\ExternalEmployerBlock;
use App\Services\TrainingOrganizationDetector;
use App\Support\LbaCfaBlocklist;
use Illuminate\Support\Str;

/**
 * Decide si une offre importee peut etre montree aux candidats Jeuncy.
 *
 * LA regle du projet (Pierre, 2026-09-15) : aucune ecole, aucun CFA, aucun
 * organisme de formation ne doit apparaitre — Jeuncy travaille avec une
 * ecole partenaire, et une annonce d'ecole concurrente affichee a nos
 * candidats les detourne. Tout le reste de l'import est secondaire.
 *
 * Six couches, de la plus sure a la moins sure. La premiere qui parle
 * l'emporte, et sa raison est conservee sur l'offre pour que l'admin puisse
 * verifier ce que le filtre ecarte.
 *
 *  1. liste blanche (SIRET de l'ecole partenaire) : jamais ecartee, sauf
 *     blocage manuel ;
 *  2. blocage manuel depuis l'admin (SIRET ou nom) : ce qui est passe entre
 *     les mailles une fois n'y repasse pas ;
 *  3. offre « deleguee » : LBA permet a un CFA de publier pour le compte
 *     d'une entreprise, le contact devient alors l'ecole — c'est exactement
 *     le cas qui nous prend des candidats ;
 *  4. code NAF de l'enseignement (meme liste que pour l'inscription des
 *     entreprises) ;
 *  5. nom present dans la liste des ~1 800 CFA de LBA, ou evoquant une
 *     ecole (memes mots que TrainingOrganizationDetector) ;
 *  6. tournures d'ecole dans la description — sous-ensemble strict du
 *     detecteur : « rentree 2026 » ou « titre RNCP » sont frequents dans
 *     de vraies offres d'entreprise et ne suffisent pas ici.
 */
class ExternalOfferFilter
{
    private const DESCRIPTION_PATTERNS = [
        'nos entreprises partenaires',
        "l'une de nos entreprises partenaires",
        'entreprise partenaire recherche',
        'entreprises partenaires recherchent',
        'pour le compte de nos entreprises',
        "pour le compte d'une entreprise partenaire",
        // « organisme de formation » et « centre de formation d'apprentis »
        // retires le 2026-09-16 : sur la premiere passe reelle, 6 des 8
        // offres ecartees par la description etaient de vrais employeurs
        // (« formation assuree par un organisme de formation partenaire »).
        'notre ecole',
        'notre campus',
        'notre cfa',
        'nos apprenants',
        'frais de scolarite',
        'integrer notre formation',
        'rejoindre notre formation',
    ];

    /** @var array<string, true> noms LBA normalises (cle = nom) */
    private array $blocklist;

    /** @var list<string> noms LBA normalises longs, cherches comme mots dans un nom */
    private array $longBlocklist;

    /** @var array<string, true> */
    private array $blockedSirets = [];

    /** @var array<string, true> */
    private array $blockedNames = [];

    /** @var array<string, true> */
    private array $whitelist;

    public function __construct(private readonly TrainingOrganizationDetector $detector)
    {
        $this->blocklist = [];
        $this->longBlocklist = [];
        foreach (LbaCfaBlocklist::NAMES as $name) {
            $normalized = self::normalize($name);
            if ($normalized === '') {
                continue;
            }
            $this->blocklist[$normalized] = true;
            // Les noms courts (« ADG », « 301 ») ne se cherchent qu'en egalite
            // stricte : en sous-chaine ils rattraperaient n'importe qui.
            if (strlen($normalized) >= 8) {
                $this->longBlocklist[] = $normalized;
            }
        }

        $this->whitelist = array_fill_keys(
            array_map(fn ($s) => preg_replace('/\D/', '', (string) $s), (array) config('services.lba.siret_whitelist')),
            true,
        );
        unset($this->whitelist['']);
    }

    // Charge les blocages manuels une fois par import plutot qu'une requete
    // par offre.
    public function loadManualBlocks(): void
    {
        $this->blockedSirets = [];
        $this->blockedNames = [];
        foreach (ExternalEmployerBlock::query()->get(['siret', 'normalized_name']) as $block) {
            if ($block->siret) {
                $this->blockedSirets[$block->siret] = true;
            }
            if ($block->normalized_name) {
                $this->blockedNames[$block->normalized_name] = true;
            }
        }
    }

    /**
     * Raison d'exclusion, ou null si l'offre peut etre montree.
     *
     * @param  array{company_name: ?string, company_siret: ?string, company_naf: ?string, description: ?string, is_delegated: bool}  $offer
     */
    public function exclusionReason(array $offer): ?string
    {
        $siret = preg_replace('/\D/', '', (string) ($offer['company_siret'] ?? '')) ?? '';
        $normalizedName = self::normalize($offer['company_name'] ?? '');

        if ($siret !== '' && isset($this->blockedSirets[$siret])) {
            return 'employeur bloque par un administrateur';
        }
        if ($normalizedName !== '' && isset($this->blockedNames[$normalizedName])) {
            return 'employeur bloque par un administrateur';
        }

        if ($siret !== '' && isset($this->whitelist[$siret])) {
            return null;
        }

        if (! empty($offer['is_delegated'])) {
            return 'offre deleguee a un CFA (le contact est l\'ecole)';
        }

        if (TrainingOrganizationDetector::isBlockedNaf($offer['company_naf'] ?? null)) {
            return 'activite d\'enseignement (NAF '.strtoupper(trim((string) $offer['company_naf'])).')';
        }

        if ($normalizedName !== '') {
            if (isset($this->blocklist[$normalizedName])) {
                return 'employeur dans la liste des CFA de La bonne alternance';
            }
            foreach ($this->longBlocklist as $known) {
                if (str_contains($normalizedName, $known)) {
                    return 'employeur dans la liste des CFA de La bonne alternance';
                }
            }
        }

        $nameReason = $this->detector->nameReason($offer['company_name'] ?? null);
        if ($nameReason !== null) {
            return $nameReason;
        }

        $description = self::normalize($offer['description'] ?? '');
        foreach (self::DESCRIPTION_PATTERNS as $needle) {
            if (str_contains($description, $needle)) {
                return "la description evoque un organisme de formation : « {$needle} »";
            }
        }

        return null;
    }

    // Minuscules, sans accents, ponctuation remplacee par des espaces,
    // espaces reduits : « L'École-Sup' » et « l ecole sup » se rejoignent.
    public static function normalize(?string $text): string
    {
        $ascii = mb_strtolower(Str::ascii((string) $text));
        $ascii = preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?? '';

        return trim(preg_replace('/\s+/', ' ', $ascii) ?? '');
    }
}
