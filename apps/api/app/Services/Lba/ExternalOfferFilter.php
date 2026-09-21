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
    // Tournures faibles, ignorees quand le texte se presente comme un
    // intermediaire de placement (GEIQ, groupement d'employeurs, interim,
    // ESN) : 109 vrais employeurs sur 491 exclusions textuelles, France
    // entiere, venaient de la (2026-09-21).
    private const INTERMEDIARY_CONTEXT = '/\b(geiq|groupements? d employeurs?|interim|interimaire|agence d emploi|manpower|adecco|randstad|synergie|proman|temporis|actual|crit|start people|samsic|leader interim|experis|esn|societe de conseil)\b/';

    private const WEAK_PATTERNS = [
        'nos entreprises partenaires',
        'l une de nos entreprises partenaires',
        'entreprise partenaire recherche',
        'entreprises partenaires recherchent',
        'pour le compte de nos entreprises',
        'pour le compte d une entreprise partenaire',
        'aucun frais de formation',
        'equipe pedagogique',
    ];

    // « equipe pedagogique » est le vocabulaire des creches et de la garde
    // d'enfants (People&Baby, Koala Kids...), pas un indice d'ecole.
    private const CHILDCARE_CONTEXT = '/\b(creche|micro creche|petite enfance|garde d enfants|assistante? maternel|eaje|jeunes enfants)\b/';

    private const DESCRIPTION_PATTERNS = [
        'nos entreprises partenaires',
        'l une de nos entreprises partenaires',
        'entreprise partenaire recherche',
        'entreprises partenaires recherchent',
        'pour le compte de nos entreprises',
        'pour le compte d une entreprise partenaire',
        // « organisme de formation » et « centre de formation d'apprentis »
        // retires le 2026-09-16 : sur la premiere passe reelle, 6 des 8
        // offres ecartees par la description etaient de vrais employeurs
        // (« formation assuree par un organisme de formation partenaire »).
        'nos apprenants',
        'integrer notre formation',
        'rejoindre notre formation',
        // Ajoutees le 2026-09-17 apres relecture des 727 offres en ligne par
        // 16 agents : 18 tournures a ZERO faux positif sur 703 vraies offres,
        // qui attrapent les 7 annonces signees par une ecole (IFRIA, PRH 360,
        // H et C Conseil, Grand Sud Formation). Texte normalise : minuscules,
        // sans accents, ponctuation remplacee par des espaces.
        'aucun frais de formation',
        'equipe pedagogique',
        'avant pendant et apres la formation',
        'avant pendant et apres votre formation',
        // France entiere (2026-09-18) : AGEPAC, Chambres de metiers (le Centre
        // d'aide a la decision relaie des artisans mais forme dans les CFA de
        // la CMA — decision de Pierre : on retire), ecoles « maison ».
        'nos formations en alternance',
        'cette offre provient du centre d aide a la decision',
        'ecole de formation interne',
        'centre de formation interne',
        'organisme de formation interne',
        'formation interne a l entreprise',
        'nous assurons votre formation',
        'une formation en alternance qui recrute',
        'cette formation prepare au titre',
        'nous vous proposons en partenariat avec',
        // Annonces anonymes d'ecoles (France entiere, 2026-09-18) : pitch de
        // formation, catalogue de titres, l'ecole qui se dit « nous ».
        'preparez en seulement',
        'beneficiez d une formation remuneree',
        'nous recrutons pour l un de nos partenaires',
        'localisation de l organisme de formation',
        'contrat en alternance pour la formation',
        'sans ecole actuelle',
        // Formation « maison » : Carrefour (CQP en magasin), Vitalliance,
        // La Poste (Formaposte), ecoles de vente des concessions.
        'nous vous proposons de vous former et d obtenir',
        'integrer nos formations',
        'enseignes de la grande distribution recrutent',
        'nous formons et accompagnons',
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
        // « notre ecole partenaire », « notre CFA en ligne partenaire » : c'est
        // l'employeur qui parle de l'ecole du candidat (12 faux positifs sur
        // 12 dans un lot KFC/ProNoia). Le possessif ne suffit que sans
        // « partenaire » a proximite.
        '/\bnotre (ecole|campus|cfa)\b(?![^.]{0,25}\bpartenaires?\b)/' => '« notre ecole »',
        '/\bcfa\b(?! partenaire)[^.]{0,80}\brecherche\b/' => 'le CFA est l\'annonceur',
        '/\bcette offre d (apprentissage|alternance) est faite pour vous\b/' => 'boilerplate d\'ecole (IFRIA)',
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
        '/\b(ifria|grand sud formation|h et c conseil|prh ?360|next ?step ?academy|disciplina|agepac|skale|my ?bs|runapp|arefip|one education|acadenice|hbc school|ifac|noveha|baticampus|asgarth|evolu ?sante|ajili formation|healthcademia|ecole d assas|family plus|koann|ef oi|actual talent|altern ?emploi|acces metiers?|form ?aou|ifp atlantique)\b/' => 'organisme de formation nomme dans le texte',
        // Gabarit redige par le CFAI / l'AFPI (pole formation UIMM) sous le nom
        // de l'entreprise d'accueil.
        '/\bles equipes (du cfai|de l afpi)\b/' => 'annonce redigee par le CFAI',
        // L'employeur forme lui-meme ses apprentis dans son propre CFA (chaine
        // de boulangeries « avec son CFA d'entreprise 100 % en ligne », La
        // Poste et Formaposte, enseignes a centre de formation maison).
        // Decision de Pierre (2026-09-18) : l'employeur proposera son CFA au
        // candidat, donc l'offre sort, meme si le poste est reel. « votre
        // CFA » exclu du groupe : c'est la tournure ordinaire d'un employeur
        // qui parle de l'ecole choisie par le candidat.
        '/\bcfa d entreprise\b/' => 'l\'employeur forme dans son propre CFA',
        '/\b(son|notre|nos|leur|leurs) (propres? )?(cfa|centre de formation)\b(?![^.]{0,25}\bpartenaires?\b)/' => 'l\'employeur forme dans son propre CFA',
        '/\b(son|notre|nos|leur|leurs) propres? (campus|ecole|academie|academy)\b/' => 'l\'employeur forme dans son propre CFA',
        '/\b(ecole|academie|academy|campus|universite|cfa|centre de formation|organisme de formation) (interne|d entreprise|maison|de l enseigne|du groupe|integre)s?\b/' => 'l\'employeur forme dans son propre CFA',
        // Burger King, « plus besoin de chercher une entreprise... nous vous
        // proposons de vous former directement au sein d'un restaurant » :
        // une proposition de formation, pas une offre d'emploi (Pierre,
        // 2026-09-18). Les autres offres de l'enseigne restent.
        '/\b(vous|te) former directement au sein (de|d un|d une|du) (restaurant|magasin|salon|agence|boutique|point de vente)s?\b/' => 'l\'employeur forme dans son propre CFA',
        '/\bformation (100 )?realisee en (restaurant|magasin|agence|boutique)\b/' => 'l\'employeur forme dans son propre CFA',
        '/\bcentre de formation [a-z0-9]+ vous propose\b/' => 'l\'employeur forme dans son propre CFA',
        '/\bnotre [a-z0-9]+ (academy|akademy|academie)\b/' => 'l\'employeur forme dans son propre CFA',
        '/\b(faurie campus|chopard academy|ecole hermes|ecole carrefour|formaposte|cfa des chefs|universite clariane|ecole de la reussite)\b/' => 'l\'employeur forme dans son propre CFA',
        // Tous les apprentis de La Poste passent par Formaposte, son CFA
        // (decision La Poste du 2026-09-18), y compris les annonces anonymes
        // « Facteur » qui ne le nomment pas.
        '/\b(groupe la poste|la poste groupe)\b/' => 'l\'employeur forme dans son propre CFA',
        // « le CFA Academie by Elior » : CFA d'entreprise nomme comme une marque.
        '/\bcfa (academie|academy) by\b/' => 'l\'employeur forme dans son propre CFA',
        // « Acto interim recherche POUR son centre de formation partenaire » :
        // l'ecole est la beneficiaire, pas « avec notre ecole partenaire ».
        '/\b(pour|au profit de) (son|sa|notre|un|une|nos|ses) (centre de formation|ecole|cfa|organisme de formation)s? partenaires?\b/' => 'recrute pour une ecole partenaire',
        '/\becole de vente [a-z]+\b/' => 'l\'employeur forme dans son propre CFA',
        '/\bformations? (se passe|se passent|se deroule|se deroulent|a lieu|ont lieu) (au sein de|dans|en) (votre|notre|le|ton) (magasin|restaurant|agence|entreprise)\b/' => 'l\'employeur forme dans son propre CFA',
        '/\bform(e|ee|es|ees|ation)\b[^.]{0,30}\bpar nos soins\b/' => 'l\'employeur forme dans son propre CFA',
        '/\bune entreprise (basee|situee|implantee) (a|en|dans|sur) [a-z0-9 ]{0,40}\brecherche (son|sa|un|une)\b/' => 'l\'ecole presente son entreprise partenaire',
        // Ecoles qui postent comme employeurs (France entiere, 2026-09-18).
        '/^.{0,40}\bcentre de formation d apprentis\b/' => 'annonce ouverte par un centre de formation d\'apprentis',
        '/\b(ecole|cfa|campus|academie|academy|institut|centre de formation|organisme de formation)\b[^.]{0,60}\brecrute pour\b/' => 'l\'organisme se declare recruteur',
        '/\b(accompagne|accompagnons|assiste|assistons) (son|notre|nos|ses|une|l) entreprises? partenaires? dans (le|leur|son|ses) recrutements?\b/' => 'l\'ecole accompagne son entreprise partenaire',
        '/\bentreprise partenaire\b[^.]{0,80}\brecherche\b/' => 'entreprise partenaire recherche',
        '/\bchambre de metiers et de l artisanat\b[^.]{0,40}\brecrute\b/' => 'la chambre de metiers recrute pour un artisan',
    ];

    // Employeurs reconnus comme organismes de formation malgre un nom qui
    // n'en a pas l'air (relecture du 2026-09-17). Sous-chaine du nom
    // normalise.
    // Vrais employeurs dont le nom contient un mot d'ecole : relais de
    // l'emploi agricole (ANEFA, 2026-09-21).
    private const NAME_EXCEPTIONS = '/\b(anefa|association nationale emploi formation agriculture)\b/';

    private const LOCAL_CFA_NAMES = [
        'prh 360',
        'prh360',
        'association regionale des entreprises alimentaires',
        'disciplina',
        'agepac',
        'runapp',
        'arefip',
        'acadenice',
        'one education',
        'chambre de metiers',
        'chambre regionale de metiers',
    ];

    // Memes conclusions quand la sous-chaine serait trop courte pour etre
    // sure (« skale », « my bs », « cma ») : ancree au debut du nom.
    private const LOCAL_CFA_NAME_REGEXES = [
        // « cma » sans « cgm » : l'armateur CMA CGM recrute aussi des apprentis.
        '/^(skale|my ?bs|cma(?! cgm)|hbc school|sepr|ef oi|a2pro|prodefi|cdfis|objectif sport|sap hestia|esup|psl|healthcademia|evolusante|actual talent|koann|france travail)\b/',
        '/\blep prive\b/',
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

    // « Téléprospecteur / BTS NDRC/TP EPC/ TP NTC/ Bachelor REM » : un titre
    // qui enumere trois diplomes ou plus est un catalogue d'ecole, quel que
    // soit le nom d'entreprise affiche (quatre societes-ecrans reperees le
    // 2026-09-18). Deux diplomes (« Licence/BTS ») restent une vraie offre.
    private const DIPLOMA_CATALOGUE_TITLE = '/\b(BTS|TP|Bachelor|Licence|BUT)\b[^\/]{0,40}\/[^\/]{0,40}\b(BTS|TP|Bachelor|Licence|BUT)\b[^\/]{0,40}\/[^\/]{0,40}\b(BTS|TP|Bachelor|Licence|BUT)\b/iu';

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
        if ($nameReason !== null && ! preg_match(self::NAME_EXCEPTIONS, $normalizedName)) {
            return $nameReason;
        }

        foreach (self::LOCAL_CFA_NAMES as $known) {
            if ($normalizedName !== '' && str_contains($normalizedName, $known)) {
                return 'employeur reconnu comme organisme de formation';
            }
        }
        foreach (self::LOCAL_CFA_NAME_REGEXES as $regex) {
            if ($normalizedName !== '' && preg_match($regex, $normalizedName)) {
                return 'employeur reconnu comme organisme de formation';
            }
        }

        $description = self::normalize($offer['description'] ?? '');
        $intermediary = preg_match(self::INTERMEDIARY_CONTEXT, $description) === 1;
        $childcare = preg_match(self::CHILDCARE_CONTEXT, $description) === 1;
        foreach (self::DESCRIPTION_PATTERNS as $needle) {
            if (! str_contains($description, $needle)) {
                continue;
            }
            if (in_array($needle, self::WEAK_PATTERNS, true) && ($intermediary || ($childcare && $needle === 'equipe pedagogique'))) {
                continue;
            }

            return "la description evoque un organisme de formation : « {$needle} »";
        }
        foreach (self::DESCRIPTION_REGEXES as $regex => $label) {
            if ($intermediary && in_array($label, ['entreprise partenaire recherche', 'ses etablissements partenaires'], true)) {
                continue;
            }
            if (preg_match($regex, $description)) {
                return "la description evoque un organisme de formation : {$label}";
            }
        }
        // « annonce ouverte par un CFA » : seulement sans employeur nomme. Un
        // artisan qui ecrit « en alternance avec le CFA de Ploufragan » n'est
        // pas un CFA (6 faux positifs sur 8 avec un nom d'employeur).
        if ($normalizedName === '' && preg_match('/^.{0,100}\bcfa\b(?! partenaire)/', $description)) {
            return 'la description evoque un organisme de formation : annonce ouverte par un CFA';
        }

        if ($normalizedName === '' && preg_match('/\b4 ?jours entreprise 1 jour ecole\b/', $description)) {
            return 'annonce anonyme au gabarit d\'une ecole (rythme 4 jours / 1 jour ecole)';
        }

        if (preg_match(self::DIPLOMA_CATALOGUE_TITLE, trim((string) ($offer['title'] ?? '')))) {
            return 'titre = catalogue de diplomes d\'une ecole';
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
