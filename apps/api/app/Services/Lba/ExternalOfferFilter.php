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
        // Ajoutees le 2026-09-17 apres relecture des 727 offres en ligne par
        // 16 agents : 18 tournures a ZERO faux positif sur 703 vraies offres,
        // qui attrapent les 7 annonces signees par une ecole (IFRIA, PRH 360,
        // H et C Conseil, Grand Sud Formation). Texte normalise : minuscules,
        // sans accents, ponctuation remplacee par des espaces.
        'rentree en formation',
        'aucun frais de formation',
        'poursuivre votre cursus',
        'equipe pedagogique',
        'avant pendant et apres la formation',
        'avant pendant et apres votre formation',
    ];

    // Memes conclusions, sous forme d'expressions regulieres (texte
    // normalise, voir normalize()). Chacune mesuree a 0 faux positif sur le
    // corpus du 2026-09-17. Les tournures « intuitives » (rncp, titre
    // professionnel, centre de formation, ecole, entreprise d'accueil,
    // organisme de formation) ont ete mesurees et REJETEES : 6 a 56 vraies
    // offres attrapees a tort chacune, surtout des GEIQ, groupements
    // d'employeurs et agences d'interim. Toute regle future doit etre
    // re-mesuree contre eux avant adoption.
    private const DESCRIPTION_REGEXES = [
        '/\brecherche pour (son|sa|notre|un|une) (entreprise|client|etablissement|societe|structure) partenaire\b/' => 'recherche pour son entreprise partenaire',
        '/\bcfa\b[^.]{0,80}\brecherche\b/' => 'le CFA est l\'annonceur',
        '/^.{0,100}\bcfa\b/' => 'annonce ouverte par un CFA',
        '/\bcette offre d (apprentissage|alternance) est faite pour vous\b/' => 'boilerplate d\'ecole (IFRIA)',
        '/\blieu (ecole|cfa|campus|centre de formation)\b/' => 'lieu = l\'ecole',
        '/\b(son|notre|nos|ses) clients? partenaires?\b/' => 'l\'employeur est le client de l\'annonceur',
        '/\bnotre partenaire (recherche|recrute)\b/' => 'notre partenaire recherche',
        '/\bde (ses|nos) (etablissements|clients) partenaires\b/' => 'ses etablissements partenaires',
        '/\b(formation|cfa|ecole|campus|academie|institut) recrute\b/' => 'l\'organisme se declare recruteur',
        // Meme conclusion quand l'ecole se nomme entre les deux mots :
        // « L'ecole NextStepAcademy recrute pour l'un de ses partenaires »
        // (passee au travers le 2026-09-18). Jusqu'a trois mots d'ecart ;
        // « formation » seul est exclu du groupe, trop courant dans une
        // phrase ordinaire (« en formation, notre entreprise recrute »).
        '/\b(cfa|ecole|campus|academie|academy|institut|centre de formation)( [a-z0-9]+){1,3} recrute\b/' => 'l\'organisme se declare recruteur',
        '/\bpre ?selection des (dossiers|candidatures) par\b/' => 'pre-selection par l\'organisme',
        '/\bpresent(e|ee|es|ees) a l entreprise\b/' => 'clause de captation',
        '/\b(ifria|grand sud formation|h et c conseil|prh ?360|next ?step ?academy)\b/' => 'organisme de formation nomme dans le texte',
        // L'employeur forme lui-meme ses apprentis dans son propre CFA (chaine
        // de boulangeries « avec son CFA d'entreprise 100 % en ligne », La
        // Poste et Formaposte, enseignes a centre de formation maison).
        // Decision de Pierre (2026-09-18) : l'employeur proposera son CFA au
        // candidat, donc l'offre sort, meme si le poste est reel. « votre
        // CFA » exclu du groupe : c'est la tournure ordinaire d'un employeur
        // qui parle de l'ecole choisie par le candidat.
        '/\bcfa d entreprise\b/' => 'l\'employeur forme dans son propre CFA',
        '/\b(son|notre|nos|leur|leurs) (propres? )?(cfa|centre de formation)\b/' => 'l\'employeur forme dans son propre CFA',
    ];

    // Employeurs reconnus comme organismes de formation malgre un nom qui
    // n'en a pas l'air (relecture du 2026-09-17). Sous-chaine du nom
    // normalise.
    private const LOCAL_CFA_NAMES = [
        'prh 360',
        'prh360',
        'association regionale des entreprises alimentaires',
    ];

    // Gabarit des annonces de l'ISCOD (ecole en ligne, 4 445 apprentis)
    // diffusees via France Travail : employeur vide, titre « Alternance
    // <poste> - <ville> (F/H) », description reduite aux missions — pas un
    // mot d'ecole dans le texte, les verificateurs les ont retrouvees mot
    // pour mot sur iscod.fr. Le corpus en comptait 38 ; une quinzaine sont
    // peut-etre de vraies entreprises restees anonymes, mais elles passent
    // par le meme emetteur. Decision (Pierre, 2026-09-17 : « l'ideal serait
    // qu'il n'y en ait aucune ») : tout le gabarit sort.
    private const ANONYMOUS_SCHOOL_TITLE = '/^alternance .+ - .+ \(F\/H\)$/iu';

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
     * @param  array{company_name: ?string, company_siret: ?string, company_naf: ?string, description: ?string, is_delegated: bool, title?: ?string, partner_label?: ?string}  $offer
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

        foreach (self::LOCAL_CFA_NAMES as $known) {
            if ($normalizedName !== '' && str_contains($normalizedName, $known)) {
                return 'employeur reconnu comme organisme de formation';
            }
        }

        $description = self::normalize($offer['description'] ?? '');
        foreach (self::DESCRIPTION_PATTERNS as $needle) {
            if (str_contains($description, $needle)) {
                return "la description evoque un organisme de formation : « {$needle} »";
            }
        }
        foreach (self::DESCRIPTION_REGEXES as $regex => $label) {
            if (preg_match($regex, $description)) {
                return "la description evoque un organisme de formation : {$label}";
            }
        }

        if ($normalizedName === ''
            && ($offer['partner_label'] ?? null) === 'France Travail'
            && preg_match(self::ANONYMOUS_SCHOOL_TITLE, trim((string) ($offer['title'] ?? '')))) {
            return 'annonce anonyme au gabarit d\'une ecole en ligne';
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
