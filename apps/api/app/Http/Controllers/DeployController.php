<?php

namespace App\Http\Controllers;

use App\Enums\ContractType;
use App\Enums\ExternalJobOfferStatus;
use App\Enums\JobOfferStatus;
use App\Enums\NotificationType;
use App\Enums\OfferSector;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\CandidateProfile;
use App\Models\CfaOrganization;
use App\Models\Company;
use App\Models\GeocodeCache;
use App\Models\JobOffer;
use App\Models\Notification;
use App\Models\OfferInterest;
use App\Models\Skill;
use App\Models\Software;
use App\Models\User;
use App\Presenters\CandidateCardPresenter;
use App\Services\AccountService;
use App\Services\AdminService;
use App\Services\ApplicationService;
use App\Services\BlockService;
use App\Services\CandidateProfileService;
use App\Services\CompanyVerificationService;
use App\Services\CvService;
use App\Services\CvthequeService;
use App\Services\DiscoverService;
use App\Services\GeocodingService;
use App\Services\InterestService;
use App\Services\JobOfferMatchService;
use App\Services\JobOfferService;
use App\Services\JwtService;
use App\Services\Lba\LbaClient;
use App\Services\Lba\LbaImportService;
use App\Services\MatchClosingService;
use App\Services\MatchScorer;
use App\Services\MatchService;
use App\Services\PaymentService;
use App\Support\MatchPerimeter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Point d'entree de deploiement pour un hebergement mutualise sans acces SSH
 * (OVH) : `php artisan migrate` ne peut pas etre lance depuis un terminal, ce
 * controleur l'expose donc via une route web protegee par un secret partage
 * (DEPLOY_TOKEN, jamais commite). Si la variable est vide, les deux routes
 * repondent 404 - inerte par defaut en local et tant qu'elle n'est pas
 * volontairement definie en production.
 */
class DeployController extends Controller
{
    // Version de cet outil de deploiement, renvoyee par /version. Sans elle, on
    // ne peut pas savoir si le controleur lui-meme a bien ete redeploye : c est
    // arrive le 2026-09-02, ou clear-cache continuait d echouer avec une version
    // corrigee censement en place. A incrementer a chaque changement ici.
    public const DEPLOY_TOOLS_VERSION = 'deploy-tools-31';

    // Perimetre de lancement du match mobile (decision du 2026-09-22) : les
    // Pyrenees-Orientales, mesurees autour de Perpignan (centre-ville).
    public const PERPIGNAN_LATITUDE = 42.6887;

    public const PERPIGNAN_LONGITUDE = 2.8948;

    // Rayons mesures autour de Perpignan, en km (voir geoStats). 100 km est
    // le rayon de recrutement maximal du modele (5 a 100 km sur l'offre) :
    // ce que verrait une offre reglee au maximum.
    public const RAYONS_KM = [10, 30, 50, 100];

    // Secret statistique : un departement qui compte moins de candidats que
    // ce seuil est fondu dans « autres ». Un comptage par departement n'est
    // pas une donnee personnelle en soi (il ne designe personne), mais un
    // effectif de 1 ou 2 est une case trop fine pour une sonde dont la
    // reponse sera copiee dans des rapports ; la regle de l'INSEE est la
    // meme (cases < 3 masquees). Sans perte pour la decision qu'elle
    // eclaire : le 66 et ses voisins a ouvrir ensuite depassent le seuil ou
    // n'ont de toute facon pas la masse pour etre ouverts.
    public const SEUIL_SECRET_STATISTIQUE = 3;

    // Age minimum sur l'application mobile (decision du 2026-09-22 ; le site
    // classique reste a 15 ans).
    public const AGE_MINIMUM_APP = 16;

    // Cle du battement du planificateur, ecrite par bootstrap/app.php a
    // chaque schedule:run. Dupliquee en dur la-bas volontairement : voir
    // le commentaire de la tache pour la raison.
    public const CLE_BATTEMENT = 'planificateur.dernier_passage';

    // Le cron OVH appelle schedule:run toutes les heures. Au-dela de 70
    // minutes sans battement, un passage a ete manque : ce n'est plus un
    // decalage d'horloge, c'est un cron qui ne tourne plus.
    public const BATTEMENT_TOLERANCE_MINUTES = 70;

    // Drapeau « lance l'import LBA au prochain passage » (voir lbaImport et
    // bootstrap/app.php). Doit rester identique des deux cotes.
    public const CLE_IMPORT_LBA_DEMANDE = 'lba.import_demande';

    // Prefixe des marqueurs ecrits par bootstrap/app.php apres chaque passe
    // reussie d'une tache. Ecrit en dur des deux cotes, volontairement :
    // meme raison que CLE_BATTEMENT, un fichier absent ne doit jamais
    // pouvoir faire tomber le cron.
    public const CLE_PASSE_PREFIXE = 'planificateur.derniere_passe.';

    // Les taches a cadence, avec leur periode. Les rappels de visio n'y
    // sont pas : ils partent a chaque passage du cron, sans marqueur.
    public const TACHES_A_MARQUEUR = [
        'job-offers:expire' => 'jour',
        'job-offers:archive-expired-trials' => 'jour',
        'cvs:archive-inactive' => 'jour',
        'job-offers:notify-matching-candidates' => 'jour',
        'cv-downloads:purge' => 'semaine',
        'lba:import' => 'jour',
    ];

    private function assertAuthorized(string $token): void
    {
        $expected = config('app.deploy_token');

        if (! $expected || ! hash_equals($expected, $token)) {
            abort(404);
        }
    }

    public function status(string $token): Response
    {
        $this->assertAuthorized($token);

        // ?geo=1 : comptages geographiques et etat du serveur (voir geoStats).
        // Greffe sur cette route plutot que declaree dans routes/web.php : un
        // seul fichier a envoyer par FTP au lieu de deux, et un fichier de
        // moins qui peut manquer a l'arrivee (lecon du 2026-09-04).
        if (request()->query('geo') === '1') {
            return $this->geoStats();
        }

        Artisan::call('migrate:status');

        return response(Artisan::output(), 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Comptages geographiques et etat du serveur, pour dimensionner le match
     * mobile (decouverte des offres par distance cote candidat, rayon de
     * recrutement sur l'offre, lancement limite au departement 66) AVANT de
     * le concevoir.
     *
     * POURQUOI. Regle du depot : ne jamais deviner, mesurer d'abord. Combien
     * de candidats ont un code postal exploitable, combien d'offres
     * partenaires ont des coordonnees, combien se trouvent a 30 km de
     * Perpignan, MySQL sait-il calculer une distance (ST_Distance_Sphere),
     * quel temps d'execution accorde l'hebergeur : chaque reponse tranche un
     * choix de conception, et aucune ne se lit sans acces SSH.
     *
     * AUCUNE DONNEE PERSONNELLE : uniquement des comptages agreges. Le code
     * postal est reduit au departement avant d'etre renvoye ; jamais un nom,
     * un email, une adresse ni une ville de residence individuelle. Cote
     * candidats, un departement de moins de SEUIL_SECRET_STATISTIQUE profils
     * est fondu dans « autres » (voir masquerPetitsEffectifs).
     *
     * Chaque bloc est protege separement : une table absente (migration pas
     * encore passee) ou une fonction SQL inconnue doit rendre CE bloc
     * « indisponible », pas faire tomber la sonde entiere.
     */
    private function geoStats(): Response
    {
        $debut = microtime(true);

        $payload = [
            'version_outils_deploiement' => self::DEPLOY_TOOLS_VERSION,
            'candidats' => $this->mesure(fn () => $this->statsCandidats()),
            'offres_jeuncy' => $this->mesure(fn () => $this->statsOffresJeuncy()),
            'offres_partenaires' => $this->mesure(fn () => $this->statsOffresPartenaires()),
            'organisations' => $this->mesure(fn () => $this->statsOrganisations()),
            'serveur' => $this->statsServeur(),
        ];

        $payload['duree_ms'] = (int) round((microtime(true) - $debut) * 1000);
        $payload['heure_serveur'] = now()->toDateTimeString();

        return response()->json($payload, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Le resultat de l'appel, ou « indisponible » avec la cause, sans jamais
     * interrompre la sonde : c'est un outil de mesure, il doit repondre meme
     * quand une partie de ce qu'il mesure manque.
     */
    private function mesure(\Closure $appel): mixed
    {
        try {
            return $appel();
        } catch (\Throwable $e) {
            // Tronque : un message de QueryException porte la requete SQL
            // entiere, inutilement longue ici (et sans donnee personnelle :
            // aucune de ces requetes ne prend de nom ni d'email en parametre).
            return 'indisponible : '.$e::class.' — '.mb_substr($e->getMessage(), 0, 200);
        }
    }

    /**
     * Departement d'un code postal francais, ou 'inconnu'.
     *
     * Public et statique pour etre teste sur des valeurs choisies : Corse
     * (2A jusqu'a 20199, 2B au-dela), outre-mer (trois caracteres), saisies
     * sales (espaces, prefixe « F- », ville tapee dans le champ). Tout ce qui
     * ne donne pas exactement cinq chiffres est 'inconnu' : mieux vaut
     * compter une saisie douteuse comme inexploitable que l'attribuer a un
     * departement au hasard.
     */
    public static function departementDuCodePostal(?string $codePostal): string
    {
        $chiffres = preg_replace('/\D/', '', (string) $codePostal);

        if (strlen($chiffres) !== 5) {
            return 'inconnu';
        }

        if (str_starts_with($chiffres, '20')) {
            return (int) $chiffres < 20200 ? '2A' : '2B';
        }

        if (str_starts_with($chiffres, '97') || str_starts_with($chiffres, '98')) {
            return substr($chiffres, 0, 3);
        }

        return substr($chiffres, 0, 2);
    }

    /**
     * Repartition par departement d'un groupement (code postal, effectif),
     * triee par effectif decroissant. Le code postal ne sort jamais d'ici :
     * seul le departement est renvoye.
     *
     * @param  iterable<object{code_postal: ?string, n: int|string}>  $lignes
     * @return array{par_departement: array<string, int>, inexploitables: int}
     */
    private static function regrouperParDepartement(iterable $lignes): array
    {
        $parDepartement = [];
        $inexploitables = 0;

        foreach ($lignes as $ligne) {
            $departement = self::departementDuCodePostal($ligne->code_postal);
            $parDepartement[$departement] = ($parDepartement[$departement] ?? 0) + (int) $ligne->n;

            // Renseigne mais illisible : une donnee a nettoyer, pas une
            // donnee absente.
            if ($departement === 'inconnu' && trim((string) $ligne->code_postal) !== '') {
                $inexploitables += (int) $ligne->n;
            }
        }

        arsort($parDepartement);

        return ['par_departement' => $parDepartement, 'inexploitables' => $inexploitables];
    }

    /**
     * Fond sous « autres » les departements dont l'effectif est inferieur a
     * SEUIL_SECRET_STATISTIQUE. Reserve aux personnes physiques (candidats) :
     * une offre ou une entreprise est publique sur le site, un candidat non.
     * « inconnu » n'est pas un departement mais une mesure de qualite de la
     * donnee : il reste tel quel, quel que soit son effectif.
     *
     * @param  array<string, int>  $parDepartement
     * @return array{par_departement: array<string, int>, departements_regroupes: int}
     */
    private static function masquerPetitsEffectifs(array $parDepartement): array
    {
        $resultat = [];
        $autres = 0;
        $regroupes = 0;

        foreach ($parDepartement as $departement => $n) {
            if ($departement !== 'inconnu' && $n < self::SEUIL_SECRET_STATISTIQUE) {
                $autres += $n;
                $regroupes++;
            } else {
                $resultat[$departement] = $n;
            }
        }

        if ($regroupes > 0) {
            $resultat['autres'] = $autres;
            arsort($resultat);
        }

        return ['par_departement' => $resultat, 'departements_regroupes' => $regroupes];
    }

    private function statsCandidats(): array
    {
        // Comptes supprimes (RGPD, conserves pour la comptabilite) et
        // suspendus sont hors du match, donc hors de ces comptages. Comptes
        // a part, pour que total + exclus reste egal a CandidateProfile::count().
        $joignables = fn () => DB::table('candidate_profiles')
            ->join('users', 'users.id', '=', 'candidate_profiles.user_id')
            ->where('users.is_suspended', false)
            ->whereNull('users.deleted_account_at');

        $exclus = DB::table('candidate_profiles')
            ->join('users', 'users.id', '=', 'candidate_profiles.user_id')
            ->where(fn ($q) => $q->where('users.is_suspended', true)->orWhereNotNull('users.deleted_account_at'))
            ->count();

        $repartition = self::regrouperParDepartement(
            $joignables()
                ->selectRaw('candidate_profiles.postal_code AS code_postal, COUNT(*) AS n')
                ->groupBy('candidate_profiles.postal_code')
                ->get()
        );
        $masque = self::masquerPetitsEffectifs($repartition['par_departement']);

        // Moins de 16 ans <=> ne le sera au plus tot que demain <=> ne a
        // partir de (aujourd'hui - 16 ans + 1 jour). Un « >= » sur une date
        // du lendemain plutot qu'un « > » sur le jour meme : SQLite compare
        // des chaines et une date stockee avec son heure (« ...-22 00:00:00 »)
        // depasserait « ...-22 », comptant un jeune de 16 ans tout juste.
        $neApres = now()->subYears(self::AGE_MINIMUM_APP)->addDay()->toDateString();

        return [
            'total' => $joignables()->count(),
            'exclus_supprimes_ou_suspendus' => $exclus,
            'par_departement' => $masque['par_departement'],
            'departements_regroupes_sous_autres' => $masque['departements_regroupes'],
            'code_postal_vide_mais_ville_renseignee' => $joignables()
                ->where(fn ($q) => $q->whereNull('candidate_profiles.postal_code')->orWhere('candidate_profiles.postal_code', ''))
                ->whereNotNull('candidate_profiles.city')
                ->where('candidate_profiles.city', '!=', '')
                ->count(),
            'code_postal_renseigne_mais_inexploitable' => $repartition['inexploitables'],
            'visibles_en_cvtheque' => $joignables()->where('candidate_profiles.is_visible_in_cvtheque', true)->count(),
            'avec_date_de_naissance' => $joignables()->whereNotNull('candidate_profiles.birth_date')->count(),
            'moins_de_'.self::AGE_MINIMUM_APP.'_ans' => $joignables()
                ->whereNotNull('candidate_profiles.birth_date')
                ->where('candidate_profiles.birth_date', '>=', $neApres)
                ->count(),
            // Texte libre (« B », « Permis B », « en cours »...) : non vide
            // est le seul critere possible tant qu'il n'y a pas de categorie.
            'avec_permis' => $joignables()
                ->whereNotNull('candidate_profiles.driving_license')
                ->where('candidate_profiles.driving_license', '!=', '')
                ->count(),
            'avec_photo' => $joignables()
                ->whereNotNull('candidate_profiles.photo_url')
                ->where('candidate_profiles.photo_url', '!=', '')
                ->count(),
            'avec_cv_depose' => $joignables()
                ->whereNotNull('candidate_profiles.cv_file_url')
                ->where('candidate_profiles.cv_file_url', '!=', '')
                ->count(),
        ];
    }

    private function statsOffresJeuncy(): array
    {
        $parStatut = DB::table('job_offers')
            ->selectRaw('status, COUNT(*) AS n')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($ligne) => [(string) $ligne->status => (int) $ligne->n])
            ->all();

        // Une offre n'a pas de code postal : celui de son proprietaire,
        // entreprise ou CFA (jamais les deux, invariant de JobOfferService).
        $codePostalProprietaire = 'COALESCE(companies.postal_code, cfa_organizations.postal_code)';

        $repartition = self::regrouperParDepartement(
            DB::table('job_offers')
                ->leftJoin('companies', 'companies.id', '=', 'job_offers.company_id')
                ->leftJoin('cfa_organizations', 'cfa_organizations.id', '=', 'job_offers.cfa_organization_id')
                ->where('job_offers.status', JobOfferStatus::PUBLISHED->value)
                ->selectRaw("{$codePostalProprietaire} AS code_postal, COUNT(*) AS n")
                ->groupByRaw($codePostalProprietaire)
                ->get()
        );

        return [
            'par_statut' => $parStatut,
            'publiees_par_departement' => $repartition['par_departement'],
            'publiees_sans_code_postal_exploitable' => $repartition['par_departement']['inconnu'] ?? 0,
        ];
    }

    private function statsOffresPartenaires(): array
    {
        $actives = fn () => DB::table('external_job_offers')
            ->where('status', ExternalJobOfferStatus::ACTIVE->value);

        $top = $actives()
            ->selectRaw('department, COUNT(*) AS n')
            ->groupBy('department')
            ->orderByDesc('n')
            ->limit(10)
            ->get()
            ->mapWithKeys(fn ($ligne) => [(string) ($ligne->department ?? 'inconnu') => (int) $ligne->n])
            ->all();

        return [
            'total' => $actives()->count(),
            'sans_coordonnees' => $actives()
                ->where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'))
                ->count(),
            'dans_le_66' => $actives()->where('department', '66')->count(),
            'autour_de_perpignan' => $this->mesure(fn () => $this->autourDePerpignan()),
            'top_10_departements' => $top,
        ];
    }

    /**
     * Offres partenaires actives a moins de 10, 30 et 50 km de Perpignan,
     * par la formule de haversine en SQL, en une seule requete.
     *
     * Volontairement en SQL et non en PHP : c'est ce que fera la decouverte
     * par distance en production, et c'est donc CE chemin qu'il faut savoir
     * possible et rapide sur le serveur. SQLite (tests) n'a pas SIN/COS/ASIN :
     * l'appelant renvoie alors « indisponible » via mesure().
     */
    private function autourDePerpignan(): array
    {
        $distanceKm = '6371 * 2 * ASIN(SQRT('
            .'POWER(SIN(RADIANS(latitude - ?) / 2), 2)'
            .' + COS(RADIANS(?)) * COS(RADIANS(latitude))'
            .' * POWER(SIN(RADIANS(longitude - ?) / 2), 2)))';

        $sommes = implode(', ', array_map(
            fn (int $km) => "SUM(CASE WHEN distance_km <= {$km} THEN 1 ELSE 0 END) AS km_{$km}",
            self::RAYONS_KM,
        ));

        $ligne = DB::selectOne(
            "SELECT {$sommes} FROM (SELECT {$distanceKm} AS distance_km FROM external_job_offers"
            .' WHERE status = ? AND latitude IS NOT NULL AND longitude IS NOT NULL) AS d',
            [
                self::PERPIGNAN_LATITUDE,
                self::PERPIGNAN_LATITUDE,
                self::PERPIGNAN_LONGITUDE,
                ExternalJobOfferStatus::ACTIVE->value,
            ],
        );

        $resultat = [];
        foreach (self::RAYONS_KM as $km) {
            $resultat["{$km}_km"] = (int) ($ligne->{"km_{$km}"} ?? 0);
        }

        return $resultat;
    }

    private function statsOrganisations(): array
    {
        $entreprises = Company::count();
        $avecSiret = Company::query()->whereNotNull('siret')->where('siret', '!=', '')->count();

        return [
            'entreprises' => [
                'total' => $entreprises,
                'avec_siret' => $avecSiret,
                'sans_siret' => $entreprises - $avecSiret,
                'publiques' => Company::query()->where('is_public', true)->count(),
            ],
            'cfa' => [
                'total' => CfaOrganization::count(),
            ],
        ];
    }

    private function statsServeur(): array
    {
        return [
            'php' => PHP_VERSION,
            'base_de_donnees' => [
                'pilote' => $this->mesure(fn () => DB::connection()->getDriverName()),
                'version' => $this->mesure(fn () => (string) DB::selectOne('SELECT VERSION() AS v')->v),
                'st_distance_sphere' => $this->stDistanceSphere(),
            ],
            'extensions' => [
                'gd' => extension_loaded('gd'),
                'imagick' => extension_loaded('imagick'),
            ],
            'php_ini' => [
                'memory_limit' => ini_get('memory_limit'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_execution_time' => ini_get('max_execution_time'),
                'max_input_time' => ini_get('max_input_time'),
            ],
            'queue_default' => config('queue.default'),
            'cache_default' => config('cache.default'),
            // Presence seulement, jamais la valeur.
            'secrets_presents' => [
                'RESEND_API_KEY' => filled(config('services.resend.key')),
                'LBA_API_KEY' => filled(config('services.lba.api_key')),
                'JWT_SECRET' => filled(config('jwt.secret')),
            ],
            'fuseau_horaire' => config('app.timezone'),
            'heure_serveur' => now()->toDateTimeString(),
        ];
    }

    // MySQL 5.7+ sait calculer une distance sur la sphere nativement ; si la
    // fonction existe sur l'hebergement, la decouverte par distance peut s'en
    // servir au lieu de la formule de haversine ecrite a la main.
    private function stDistanceSphere(): array
    {
        try {
            $metres = DB::selectOne('SELECT ST_Distance_Sphere(POINT(0, 0), POINT(1, 1)) AS m')->m;

            return ['disponible' => true, 'metres_entre_0_0_et_1_1' => (int) round((float) $metres)];
        } catch (\Throwable $e) {
            return ['disponible' => false, 'erreur' => mb_substr($e->getMessage(), 0, 160)];
        }
    }

    public function migrate(string $token): Response
    {
        $this->assertAuthorized($token);

        Artisan::call('migrate', ['--force' => true]);

        return response(Artisan::output(), 200, ['Content-Type' => 'text/plain']);
    }

    public function clearCache(string $token): Response
    {
        $this->assertAuthorized($token);

        Artisan::call('optimize:clear');
        $sortie = Artisan::output();

        // OPcache garde en memoire le bytecode des fichiers PHP et
        // optimize:clear n'y touche pas : sur un hebergement qui ne revalide
        // pas les dates de modification, du code fraichement televerse peut
        // continuer d'etre execute dans son ancienne version. Le vider ici est
        // sans effet quand l'extension est absente — c'est le cas sur
        // l'hebergement actuel — mais reste une precaution utile ailleurs.
        //
        // ENTIEREMENT PROTEGE, et ce n'est pas de la prudence de principe :
        // la premiere version de ce bloc appelait opcache_reset() sans filet.
        // Quand l'API OPcache est restreinte par l'hebergeur, l'appel emet un
        // avertissement que Laravel transforme en exception, et le vidage de
        // cache entier repondait 500 — cassant l'outil dont on se sert
        // justement pour reparer un deploiement. Constate le 2026-09-02.
        $sortie .= PHP_EOL.'OPcache : ';
        try {
            if (! function_exists('opcache_reset')) {
                $sortie .= 'extension absente sur cet hebergement, rien a vider';
            } elseif (@opcache_reset()) {
                $sortie .= 'vide (le code PHP fraichement televerse est desormais actif)';
            } else {
                $sortie .= 'non vide : API restreinte par l\'hebergeur';
            }
        } catch (\Throwable $e) {
            $sortie .= 'indisponible ('.$e->getMessage().')';
        }

        return response($sortie.PHP_EOL, 200, ['Content-Type' => 'text/plain']);
    }

    // Quelle version du code tourne REELLEMENT sur le serveur ?
    //
    // Sans ce controle, on ne peut pas distinguer un correctif inefficace d'un
    // correctif jamais execute — doute qui a coute trois allers-retours de
    // deploiement le 2026-09-02. Empreinte et date de modification de chaque
    // fichier sensible, pour comparer avec le depot en une seconde.
    public function version(string $token): Response
    {
        $this->assertAuthorized($token);

        $fichiers = [
            'app/Http/Controllers/DeployController.php',
            'routes/web.php',
            'app/Services/CvService.php',
            'app/Support/SquarePhoto.php',
            'app/Services/CvthequeService.php',
            // Age du candidat dans la CVtheque : l'accesseur, la selection
            // de la date et les regles de validation doivent arriver ensemble.
            'app/Models/CandidateProfile.php',
            'app/Http/Requests/Cvtheque/SearchCvthequeRequest.php',
            'app/Http/Requests/CandidateProfile/StoreCandidateProfileRequest.php',
            'app/Http/Requests/CandidateProfile/UpdateCandidateProfileRequest.php',
            // Role STAFF : equipe Jeuncy, lecture de la CVtheque sans admin.
            'app/Enums/UserRole.php',
            // Son absence a coute une matinee : une valeur d'enum manquante ne
            // se voit nulle part, elle fait juste echouer l'insertion en
            // silence, dans un try/catch prevu pour proteger autre chose.
            'app/Enums/NotificationType.php',
            'app/Services/SubscriptionService.php',
            // Suivi du chiffre d'affaires des abonnements : la valeur
            // d'enum, le champ d'idempotence et les deux services qui
            // enregistrent puis totalisent doivent arriver ensemble. Une
            // seule absence et les prelevements mensuels disparaissent du
            // CA sans qu'aucune erreur ne le signale.
            'app/Enums/PaymentType.php',
            'app/Models/Payment.php',
            'database/migrations/2026_09_10_100001_record_subscription_invoices_in_payments.php',
            'app/Http/Controllers/Admin/UserController.php',
            'routes/api/cvtheque.php',
            'app/Services/CvImportService.php',
            'app/Services/CandidateProfileService.php',
            // Correspondance offre <-> candidat, dans les deux sens.
            'app/Services/JobOfferMatchService.php',
            // Ils portent l'appel a la notification au moment de publier.
            // Leur absence de cette liste a coute quatre allers-retours :
            // une version perimee notifie simplement jamais, sans erreur.
            'app/Services/JobOfferService.php',
            // Mise en ligne au mois : le prix achete une periode, plus une
            // publication definitive. La commande porte le preavis ET le
            // retrait, la config porte la duree. Une commande absente ne
            // produit aucune erreur — le cron passe et rien ne se fait.
            'app/Console/Commands/ExpireJobOffers.php',
            'config/services.php',
            'app/Services/PaymentService.php',
            'app/Console/Commands/NotifyCandidatesOfMatchingOffers.php',
            // Outil de correction des noms de candidats : ces quatre fichiers
            // doivent arriver ensemble, et leur absence produisait une erreur
            // indistinguable d'un bug de code.
            'app/Services/AdminService.php',
            // Email de bienvenue : la methode et ses deux points d'appel.
            'app/Services/MailService.php',
            'app/Services/AuthService.php',
            'app/Http/Controllers/Admin/CandidateProfileController.php',
            'app/Http/Requests/Admin/ListCandidateProfilesRequest.php',
            'app/Http/Requests/Admin/UpdateCandidateNameRequest.php',
            'routes/api/admin.php',
            // Mode mobile de l'authentification : le refresh token part dans le
            // corps JSON pour un client natif, et /auth/refresh ignore alors le
            // cookie (garde anti-XSS, voir MobileAuthTest). Une version perimee
            // de ce fichier deconnecte l'application au bout de 15 minutes sans
            // aucune erreur visible — panne exactement du genre de celles qui
            // ont coute quatre allers-retours en septembre.
            'app/Http/Controllers/Auth/AuthController.php',
            // Age minimum a l'inscription (case « 15 ans ou plus »).
            'app/Http/Requests/Auth/RegisterRequest.php',
            'resources/views/cv/template.blade.php',
            // Jeuncy gratuit pour les entreprises + inscription CFA fermee
            // (2026-09-15). Huit fichiers, tous necessaires : la valeur
            // d'enum et sa migration (sans elles, publier gratuitement
            // echoue a l'insertion), la route et le controleur (404 sinon),
            // le detecteur d'ecoles et le service qui l'appelle (sans le
            // premier, le second plante au demarrage), la commande de fin
            // d'essai (sans elle, l'offre de l'ecole partenaire est retiree
            // le 19 septembre).
            'app/Enums/PaymentStatus.php',
            'database/migrations/2026_09_15_100000_add_free_to_job_offers_payment_status_enum.php',
            'routes/api/job-offers.php',
            'app/Http/Controllers/JobOfferController.php',
            'app/Services/TrainingOrganizationDetector.php',
            'app/Services/CompanyService.php',
            'app/Console/Commands/ArchiveExpiredTrialOffers.php',
            // Import La bonne alternance (2026-09-15) : treize fichiers, tous
            // necessaires. La migration et les modeles (sans eux, l'import
            // plante a la premiere ecriture), le lecteur JSON en flux et la
            // liste des CFA (sans elle, le filtre plante au demarrage), les
            // services, la commande, les deux controleurs, la requete et les
            // routes (404 cote site sinon).
            'database/migrations/2026_09_15_120000_create_external_job_offers_table.php',
            // Retrait manuel d'une offre, qui survit aux imports (2026-09-17).
            'database/migrations/2026_09_17_090000_add_admin_exclusion_to_external_job_offers.php',
            'app/Enums/ExternalJobOfferStatus.php',
            'app/Models/ExternalJobOffer.php',
            'app/Models/ExternalEmployerBlock.php',
            'app/Support/JsonArrayStreamer.php',
            'app/Support/LbaCfaBlocklist.php',
            'app/Services/TrainingOrganizationDetector.php',
            'app/Services/Lba/LbaClient.php',
            'app/Services/Lba/LbaOfferMapper.php',
            'app/Services/Lba/ExternalOfferFilter.php',
            'app/Services/Lba/LbaImportService.php',
            'app/Services/ExternalJobOfferService.php',
            'app/Console/Commands/ImportLbaOffers.php',
            'app/Http/Controllers/PublicExternalJobOfferController.php',
            'app/Http/Controllers/Admin/ExternalJobOfferController.php',
            'app/Http/Requests/ExternalJobOffer/SearchExternalJobOffersRequest.php',
            'bootstrap/app.php',
            'config/cors.php',

            // ================================================================
            // LOT 1 DU MODELE MATCH (2026-09-22). Cent onze fichiers, et la
            // liste est longue A DESSEIN : les deux pannes les plus cheres de
            // septembre (2026-09-04, 2026-09-08) venaient de fichiers ABSENTS
            // du serveur, pas de code fautif — un enum sans sa valeur, un
            // service sans son cablage. Un fichier manquant ne produit aucune
            // erreur lisible : il produit une fonctionnalite qui ne fait
            // simplement rien. Tout ce que ce lot touche est donc surveille,
            // y compris les fichiers seulement MODIFIES pour brancher un
            // appel — c'est exactement la ou etait la panne, deux fois.
            // ================================================================

            // Migrations. Sans elles, chaque ecriture echoue a la premiere
            // colonne inconnue ; 100010 porte la valeur d'enum MySQL des
            // trois nouveaux types de notification (le selftest la prouve).
            'database/migrations/2026_09_22_100000_add_match_fields_to_candidate_profiles_table.php',
            'database/migrations/2026_09_22_100001_add_match_fields_to_job_offers_table.php',
            'database/migrations/2026_09_22_100002_add_verification_and_coordinates_to_organizations_tables.php',
            'database/migrations/2026_09_22_100003_add_age_confirmed_at_to_users_table.php',
            'database/migrations/2026_09_22_100004_create_geocode_cache_table.php',
            'database/migrations/2026_09_22_100005_create_offer_interests_table.php',
            'database/migrations/2026_09_22_100006_add_match_fields_to_applications_table.php',
            'database/migrations/2026_09_22_100007_create_external_interests_table.php',
            'database/migrations/2026_09_22_100008_create_user_blocks_table.php',
            'database/migrations/2026_09_22_100009_create_reports_table.php',
            'database/migrations/2026_09_22_100010_add_match_types_to_notifications_type_enum.php',
            'database/migrations/2026_09_22_100011_add_coordinates_index_to_external_job_offers_table.php',
            // Lot 4 : relances et moderation (MOBILE.md §5 et §7).
            'database/migrations/2026_09_23_100000_add_match_reminder_to_notifications_type_enum.php',
            'database/migrations/2026_09_23_100001_add_reminder_tracking_to_match_tables.php',

            // Enums. Une valeur absente fait echouer l'insertion en silence.
            'app/Enums/OfferSector.php',
            'app/Enums/VerificationStatus.php',
            'app/Enums/InterestDecision.php',
            'app/Enums/ExternalInterestDecision.php',
            'app/Enums/MatchClosedReason.php',
            'app/Enums/MatchReminderStage.php',
            'app/Enums/ApplicationSource.php',
            'app/Enums/DrivingLicenseCategory.php',
            'app/Enums/ReportContext.php',

            // Modeles : les cinq nouveaux et les sept modifies (colonnes
            // fillable, casts, $hidden, relations).
            'app/Models/OfferInterest.php',
            'app/Models/ExternalInterest.php',
            'app/Models/UserBlock.php',
            'app/Models/Report.php',
            'app/Models/GeocodeCache.php',
            'app/Models/JobOffer.php',
            'app/Models/Company.php',
            'app/Models/CfaOrganization.php',
            'app/Models/Application.php',
            'app/Models/User.php',
            'app/Models/ExternalJobOffer.php',

            // Socle partage. Sans Haversine ou MatchPerimeter, les services
            // qui les appellent plantent au demarrage du conteneur — panne
            // bruyante, celle-la, mais totale.
            'app/Support/Haversine.php',
            'app/Support/PostalCodes.php',
            'app/Support/MatchPerimeter.php',
            'app/Presenters/CandidateCardPresenter.php',
            'app/Rules/ValidSiret.php',

            // Services. CvthequeService, AccountService, ApplicationService,
            // JobOfferService, CandidateProfileService, CompanyService,
            // CfaOrganizationService, AuthService, MailService,
            // TrainingOrganizationDetector et JobOfferMatchService sont
            // deja plus haut dans cette liste : ils y restent, une entree en
            // double ne coute qu'une ligne de JSON.
            'app/Services/GeocodingService.php',
            'app/Services/CompanyVerificationService.php',
            'app/Services/MatchClosingService.php',
            'app/Services/BlockService.php',
            'app/Services/MatchScorer.php',
            'app/Services/DiscoverService.php',
            'app/Services/InterestService.php',
            'app/Services/MatchService.php',
            'app/Services/ExternalInterestService.php',
            'app/Services/ReportService.php',
            'app/Services/AccountService.php',
            'app/Services/ApplicationService.php',
            'app/Services/CfaOrganizationService.php',

            // Commandes : geocode:backfill rattrape les lignes sans
            // coordonnees, candidates:migrate-driving-license reprend les 89
            // textes de permis. Une commande absente ne produit aucune
            // erreur — le cron passe et rien ne se fait.
            'app/Console/Commands/GeocodeBackfill.php',
            'app/Console/Commands/MigrateDrivingLicense.php',
            // Lot 4 : la cascade de relances et les trois files de moderation.
            'app/Console/Commands/RemindMatches.php',
            'app/Services/MatchReminderService.php',
            'app/Services/AdminModerationService.php',
            'app/Services/EmployerResponseStats.php',
            'app/Http/Controllers/Admin/ModerationController.php',
            'app/Http/Requests/Admin/DecideVerificationRequest.php',

            // Middleware des 16 ans. Son absence ne casse pas le site : elle
            // ouvre Decouvrir aux mineurs de moins de 16 ans, sans un mot.
            'app/Http/Middleware/EnsureMatchAge.php',

            // Controleurs.
            'app/Http/Controllers/DiscoverController.php',
            'app/Http/Controllers/InterestController.php',
            'app/Http/Controllers/MatchController.php',
            'app/Http/Controllers/ExternalInterestController.php',
            'app/Http/Controllers/BlockController.php',
            'app/Http/Controllers/ReportController.php',
            'app/Http/Controllers/ApplicationController.php',
            'app/Http/Controllers/CandidateProfileController.php',
            'app/Http/Controllers/CvthequeController.php',

            // Form Requests. Une requete absente = 500 a la resolution du
            // controleur, donc une route entiere morte.
            'app/Http/Requests/CandidateProfile/UpdateCandidatePreferencesRequest.php',
            'app/Http/Requests/CandidateProfile/UpdateCandidateLocationRequest.php',
            'app/Http/Requests/JobOffer/StoreExpressJobOfferRequest.php',
            'app/Http/Requests/JobOffer/StoreJobOfferRequest.php',
            'app/Http/Requests/JobOffer/UpdateJobOfferRequest.php',
            'app/Http/Requests/Company/StoreCompanyRequest.php',
            'app/Http/Requests/Company/UpdateCompanyRequest.php',
            'app/Http/Requests/CfaOrganization/StoreCfaOrganizationRequest.php',
            'app/Http/Requests/CfaOrganization/UpdateCfaOrganizationRequest.php',
            'app/Http/Requests/Discover/DiscoverOffersRequest.php',
            'app/Http/Requests/Discover/DiscoverCandidatesRequest.php',
            'app/Http/Requests/Interest/StoreInterestRequest.php',
            'app/Http/Requests/Interest/PassInterestsRequest.php',
            'app/Http/Requests/ExternalInterest/StoreExternalInterestRequest.php',
            'app/Http/Requests/Block/StoreBlockRequest.php',
            'app/Http/Requests/Report/StoreReportRequest.php',

            // Routes. routes/api.php porte les cinq nouveaux require : sans
            // lui, tout le lot repond 404 alors que chaque fichier est la.
            'routes/api.php',
            'routes/api/discover.php',
            'routes/api/interests.php',
            'routes/api/matches.php',
            'routes/api/external-interests.php',
            'routes/api/blocks-reports.php',
            'routes/api/candidate-profile.php',
        ];

        $etat = [];
        foreach ($fichiers as $chemin) {
            $absolu = base_path($chemin);
            $etat[$chemin] = is_file($absolu)
                ? [
                    'empreinte' => substr(hash_file('sha256', $absolu), 0, 16),
                    'modifie_le' => date('Y-m-d H:i:s', filemtime($absolu)),
                    'octets' => filesize($absolu),
                ]
                : 'ABSENT';
        }

        return response()->json([
            'version_outils_deploiement' => self::DEPLOY_TOOLS_VERSION,
            'version_moteur_cv' => CvService::LAYOUT_VERSION,
            'opcache_actif' => function_exists('opcache_get_status')
                && is_array(@opcache_get_status(false)),
            'php' => PHP_VERSION,
            'fichiers' => $etat,
            'heure_serveur' => now()->toDateTimeString(),
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    // Execute les chemins de code sensibles et renvoie l'exception REELLE quand
    // l'un d'eux echoue.
    //
    // POURQUOI. Le frontend n'affiche qu'un message generique, les logs
    // Laravel sont inaccessibles sans SSH, et reproduire en local ne sert a
    // rien puisque les tests passent. Il ne restait que la devinette : deux
    // diagnostics faux de suite, plusieurs deploiements pour rien. Une erreur
    // qu'on ne peut pas lire coute plus cher que la route qui la lit.
    //
    // AUCUNE DONNEE PERSONNELLE n'est renvoyee : seulement des comptages et le
    // message d'exception. Les requetes concernees ne portent ni nom ni email
    // dans leurs parametres. Protegee par le meme DEPLOY_TOKEN que le reste.
    public function selfTest(string $token): Response
    {
        $this->assertAuthorized($token);

        return response()->json([
            'version_outils_deploiement' => self::DEPLOY_TOOLS_VERSION,
            'php' => PHP_VERSION,
            'tests' => [
                'admin_candidats_filtre' => $this->essai(
                    fn () => app(AdminService::class)->listCandidateProfiles(['suspicious' => true])->total()
                ),
                'admin_candidats_sans_filtre' => $this->essai(
                    fn () => app(AdminService::class)->listCandidateProfiles([])->total()
                ),
                'admin_service_autonome' => $this->essai(
                    fn () => app(AdminService::class)->nameLooksImplausible('Permis', 'B') ? 'signale' : 'NON SIGNALE'
                ),
                'nom_reel_non_signale' => $this->essai(
                    fn () => app(AdminService::class)->nameLooksImplausible('Rostom', 'Ghazli') ? 'SIGNALE A TORT' : 'ok'
                ),
                'referentiel' => $this->essai(
                    fn () => Skill::count().' competences, '.Software::count().' logiciels'
                ),
                'profils_candidats' => $this->essai(fn () => CandidateProfile::count().' profils'),

                // Ce que ->total() ne fait pas : produire la reponse.
                'serialisation_filtre' => $this->essai(
                    fn () => $this->tailleJson(app(AdminService::class)->listCandidateProfiles(['suspicious' => true]))
                ),
                'serialisation_sans_filtre' => $this->essai(
                    fn () => $this->tailleJson(app(AdminService::class)->listCandidateProfiles([]))
                ),
                'encodage_des_profils' => $this->essai(fn () => $this->profilsMalEncodes()),

                // ---- Lot 1 du modele match (2026-09-22) ----
                //
                // Une empreinte prouve qu'un fichier est la ; ces essais
                // prouvent que le code s'EXECUTE. Ils vont jusqu'a
                // l'insertion, dans une transaction annulee : aucune donnee
                // reelle n'est touchee, aucun email ne part.
                'cablage_match' => $this->essai(fn () => $this->cablageDuMatch()),
                'perimetre' => $this->essai(fn () => MatchPerimeter::departments() ?: ['(aucun — Decouvrir est ferme)']),
                'cfa_verifiee' => $this->essai(fn () => $this->etatDesVerifications()),
                'enum_notification_mysql' => $this->essai(fn () => $this->enumNotificationEcritEnBase()),
                'geocodage_simule' => $this->essai(fn () => $this->geocodageSansReseau()),
                'parcours_match' => $this->essai(fn () => $this->parcoursMatch()),
                // Seul essai qui sort sur le reseau, et le dernier : une
                // panne de l'IGN ne doit pas empecher de lire tout le reste.
                'geocodeur' => $this->essai(fn () => $this->geocodeurReel()),
            ],
            'heure_serveur' => now()->toDateTimeString(),
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Les services du match recoivent-ils bien leurs collaborateurs ?
     *
     * Meme raison d'etre que cablageMatchService : une version perimee de
     * ces fichiers se construit sans la moindre erreur et n'appelle
     * simplement jamais ce qu'elle devrait. Ici, en plus, une classe absente
     * (celles du socle partage) ferait echouer la resolution du conteneur —
     * ce que l'essai rapporte au lieu de renvoyer 500.
     *
     * @return array<string, string>
     */
    private function cablageDuMatch(): array
    {
        $attendus = [
            JobOfferMatchService::class => [MatchScorer::class],
            DiscoverService::class => [CandidateCardPresenter::class, BlockService::class],
            CvthequeService::class => [CandidateCardPresenter::class, CompanyVerificationService::class],
            MatchService::class => [CandidateCardPresenter::class],
            InterestService::class => [MatchService::class, BlockService::class],
            ApplicationService::class => [MatchClosingService::class, MatchService::class],
            AccountService::class => [MatchClosingService::class],
        ];

        $etat = [];

        foreach ($attendus as $classe => $collaborateurs) {
            $court = class_basename($classe);

            if (! class_exists($classe)) {
                $etat[$court] = 'CLASSE ABSENTE';

                continue;
            }

            $constructeur = (new \ReflectionClass($classe))->getConstructor();
            $recus = [];

            foreach ($constructeur?->getParameters() ?? [] as $parametre) {
                $type = $parametre->getType();
                if ($type instanceof \ReflectionNamedType) {
                    $recus[] = $type->getName();
                }
            }

            $manquants = array_values(array_diff($collaborateurs, $recus));

            $etat[$court] = $manquants === []
                ? 'ok'
                : 'NON CABLE — manque '.implode(', ', array_map('class_basename', $manquants));
        }

        return $etat;
    }

    /**
     * Ce que la migration 100002 a reellement fait en base, et ce qu'il reste
     * a saisir a la main.
     *
     * Sous RefreshDatabase les tests tournent sur une base vide : la partie
     * DONNEES de cette migration (les CFA existants passent VERIFIED) ne peut
     * etre prouvee qu'ici, en production.
     *
     * @return array<string, mixed>
     */
    private function etatDesVerifications(): array
    {
        return [
            'cfa_verifies' => CfaOrganization::where('verification_status', VerificationStatus::VERIFIED->value)->count(),
            'cfa_en_attente' => CfaOrganization::where('verification_status', VerificationStatus::PENDING->value)->count(),
            'entreprises_verifiees' => Company::where('verification_status', VerificationStatus::VERIFIED->value)->count(),
            'entreprises_en_attente' => Company::where('verification_status', VerificationStatus::PENDING->value)->count(),
            // Attendu : 1 tant qu'IDA n'a pas saisi le code postal de son
            // offre. Une offre sans coordonnees n'entre dans aucune pile et
            // repond JOB_OFFER_NOT_LOCATED cote employeur.
            'offres_publiees_sans_coordonnees' => JobOffer::where('status', JobOfferStatus::PUBLISHED->value)
                ->where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'))
                ->count(),
            'communes_en_cache' => DB::table('geocode_cache')->count(),
        ];
    }

    /**
     * La valeur d'enum NEW_MATCH est-elle acceptee par la colonne MySQL ?
     *
     * C'est LA panne du 2026-09-08 : sans la valeur dans l'enum de la
     * colonne, l'insertion echouait, et le try/catch qui protege
     * l'enregistrement d'un profil avalait l'exception. SQLite ne prouve
     * rien la-dessus, MySQL seul le peut. Ecriture reelle puis annulation.
     */
    private function enumNotificationEcritEnBase(): string
    {
        return $this->dansUneTransactionAnnulee(function () {
            $sonde = $this->compteSonde('sonde-enum');
            $poses = [];

            foreach ([NotificationType::NEW_MATCH, NotificationType::INTEREST_RECEIVED, NotificationType::MATCH_CLOSED] as $type) {
                Notification::create([
                    'user_id' => $sonde->id,
                    'type' => $type,
                    'message' => 'sonde de deploiement',
                    'link' => '/mes-candidatures',
                ]);
                $poses[] = $type->value;
            }

            return implode(', ', $poses).' : acceptes par la colonne';
        });
    }

    /**
     * Le geocodage traverse-t-il bien le cache, sans reseau ?
     *
     * Une commune deja resolue ne doit JAMAIS ressortir sur le reseau : c'est
     * ce qui rend tenable un geocodage synchrone sur un hebergement sans
     * worker. On seme la ligne de cache puis on demande la meme commune.
     */
    private function geocodageSansReseau(): string
    {
        return $this->dansUneTransactionAnnulee(function () {
            $codePostal = '66000';
            $commune = 'Perpignan';

            GeocodeCache::updateOrCreate(
                [
                    'postal_code' => GeocodingService::normalizePostalCode($codePostal),
                    'city_normalized' => GeocodingService::normalizeCity($commune),
                ],
                ['latitude' => self::PERPIGNAN_LATITUDE, 'longitude' => self::PERPIGNAN_LONGITUDE, 'resolved_at' => now()],
            );

            $coordonnees = app(GeocodingService::class)->geocode($codePostal, $commune);

            if ($coordonnees === null) {
                throw new \RuntimeException('le cache n\'a pas ete lu : geocode() a rendu null');
            }

            return sprintf('cache lu sans reseau : %.4f, %.4f', $coordonnees['lat'], $coordonnees['lng']);
        });
    }

    /**
     * Appel REEL au geocodeur de l'IGN. Le seul essai qui sort sur le
     * reseau, et le seul qui puisse etre lent : quatre secondes de timeout.
     * Une panne rend « indisponible » plutot qu'une exception — c'est
     * precisement le comportement attendu du service.
     */
    private function geocodeurReel(): string
    {
        // Dans une transaction annulee comme les autres : le service met sa
        // reponse en cache, et une sonde ne doit rien laisser derriere elle —
        // pas meme une ligne utile. C'est geocode:backfill qui peuple le
        // cache, volontairement et en une passe.
        return $this->dansUneTransactionAnnulee(function () {
            $coordonnees = app(GeocodingService::class)->geocode('66000', 'Perpignan');

            return $coordonnees === null
                ? 'INDISPONIBLE — l\'IGN n\'a pas repondu ou ne connait pas la commune (geocode:backfill rattrapera)'
                : sprintf('%.5f, %.5f', $coordonnees['lat'], $coordonnees['lng']);
        });
    }

    /**
     * Le parcours complet du match, par le NOYAU HTTP.
     *
     * Le piege du 2026-09-04 est ecrit noir sur blanc dans CLAUDE.md : le
     * selftest appelait le SERVICE sans passer par le controleur, donc il
     * validait precisement la partie qui marchait. Ici chaque etape part
     * d'une vraie requete HTTP avec un vrai jeton : middleware de role,
     * middleware des 16 ans, Form Request, controleur, service, insertion.
     *
     * Tout se passe dans une transaction annulee, et la cle Resend est mise
     * a null le temps de l'essai : un match cree ici ne doit envoyer aucun
     * email a personne.
     *
     * @return array<string, mixed>
     */
    private function parcoursMatch(): array
    {
        $cleResend = config('services.resend.key');
        config(['services.resend.key' => null]);
        $requeteInitiale = request();

        try {
            return $this->dansUneTransactionAnnulee(function () {
                [$employeur, $offre, $candidat, $profil] = $this->semerLeParcours();

                $jetonEmployeur = app(JwtService::class)->issueAccessToken($employeur);
                $jetonCandidat = app(JwtService::class)->issueAccessToken($candidat);

                $etapes = [];

                $pile = $this->appelApi('GET', '/api/discover/offers', $jetonCandidat);
                $etapes['discover_offers'] = $this->resumePile($pile, $offre->id);

                $deck = $this->appelApi('GET', '/api/discover/candidates?job_offer_id='.$offre->id, $jetonEmployeur);
                $etapes['discover_candidates'] = $this->resumeDeck($deck, $profil->id);

                $cote1 = $this->appelApi('POST', '/api/interests', $jetonEmployeur, [
                    'job_offer_id' => $offre->id,
                    'candidate_profile_id' => $profil->id,
                ]);
                $etapes['interet_employeur'] = [
                    'statut' => $cote1['statut'],
                    'matched' => $cote1['corps']['data']['matched'] ?? null,
                    'attendu' => 'statut 201, matched false',
                ];

                $cote2 = $this->appelApi('POST', '/api/interests', $jetonCandidat, ['job_offer_id' => $offre->id]);
                $etapes['interet_candidat'] = [
                    'statut' => $cote2['statut'],
                    'matched' => $cote2['corps']['data']['matched'] ?? null,
                    'attendu' => 'statut 201, matched true',
                ];

                $etapes['match_en_base'] = OfferInterest::whereNotNull('matched_at')
                    ->where('job_offer_id', $offre->id)
                    ->count().' ligne(s) matchee(s)';

                $matchsCandidat = $this->appelApi('GET', '/api/matches', $jetonCandidat);
                $etapes['matches_candidat'] = [
                    'statut' => $matchsCandidat['statut'],
                    'lignes' => is_array($matchsCandidat['corps']['data'] ?? null) ? count($matchsCandidat['corps']['data']) : null,
                ];

                $matchsEmployeur = $this->appelApi('GET', '/api/matches', $jetonEmployeur);
                $etapes['matches_employeur'] = [
                    'statut' => $matchsEmployeur['statut'],
                    'lignes' => is_array($matchsEmployeur['corps']['data'] ?? null) ? count($matchsEmployeur['corps']['data']) : null,
                ];

                $etapes['notifications_creees'] = Notification::where('user_id', $candidat->id)
                    ->orWhere('user_id', $employeur->id)
                    ->count();

                return $etapes;
            });
        } finally {
            config(['services.resend.key' => $cleResend]);
            // Le noyau a remplace l'instance 'request' du conteneur a chaque
            // sous-requete : sans cette remise en place, tout ce qui lit
            // request() apres cet essai lirait la derniere sous-requete.
            app()->instance('request', $requeteInitiale);
        }
    }

    /**
     * Employeur VERIFIED avec offre publiee geolocalisee dans le 66, et
     * candidat de 18 ans a ~5 km.
     *
     * Rien n'utilise de factory : database/factories n'est pas deploye en
     * production et fakerphp n'y est pas installe (composer --no-dev).
     *
     * @return array{0: User, 1: JobOffer, 2: User, 3: CandidateProfile}
     */
    private function semerLeParcours(): array
    {
        $employeur = $this->compteSonde('sonde-employeur', UserRole::COMPANY);
        $entreprise = Company::create([
            'user_id' => $employeur->id,
            'name' => 'Sonde de deploiement',
            'siret' => '00000000000000',
            'city' => 'Perpignan',
            'postal_code' => '66000',
        ]);
        $entreprise->verification_status = VerificationStatus::VERIFIED;
        $entreprise->verified_at = now();
        $entreprise->latitude = self::PERPIGNAN_LATITUDE;
        $entreprise->longitude = self::PERPIGNAN_LONGITUDE;
        $entreprise->saveQuietly();

        $offre = JobOffer::create([
            'company_id' => $entreprise->id,
            'title' => 'Sonde de deploiement',
            'description' => 'Offre de sonde, annulee avec la transaction.',
            'contract_type' => ContractType::ALTERNANCE,
            'city' => 'Perpignan',
            'postal_code' => '66000',
            'sector' => OfferSector::COMMERCE,
            'recruitment_radius_km' => 30,
            'minimum_age' => 16,
        ]);
        $offre->status = JobOfferStatus::PUBLISHED;
        $offre->payment_status = PaymentStatus::FREE;
        $offre->published_at = now();
        $offre->applications_unlocked_at = now();
        $offre->latitude = self::PERPIGNAN_LATITUDE;
        $offre->longitude = self::PERPIGNAN_LONGITUDE;
        $offre->saveQuietly();

        $candidat = $this->compteSonde('sonde-candidat');
        $profil = CandidateProfile::create([
            'user_id' => $candidat->id,
            'first_name' => 'Sonde',
            'last_name' => 'Deploiement',
            'birth_date' => now()->subYears(18)->toDateString(),
            'city' => 'Perpignan',
            'postal_code' => '66000',
            'is_visible_in_cvtheque' => true,
            'search_radius_km' => 30,
            'mobility_radius_km' => 30,
        ]);
        // ~5 km au nord de Perpignan : assez pres pour entrer dans les deux
        // rayons, assez loin pour que distance_km ne soit pas zero — un zero
        // passerait aussi bien avec une haversine cassee.
        $profil->latitude = round(self::PERPIGNAN_LATITUDE + 0.045, 2);
        $profil->longitude = round(self::PERPIGNAN_LONGITUDE, 2);
        $profil->saveQuietly();

        return [$employeur, $offre, $candidat, $profil];
    }

    /**
     * Compte jetable, cree DANS la transaction annulee. L'email porte un
     * domaine reserve aux exemples (RFC 2606) : meme si une annulation
     * echouait, aucun message ne pourrait partir vers une vraie boite.
     */
    private function compteSonde(string $prefixe, UserRole $role = UserRole::CANDIDATE): User
    {
        return User::create([
            'email' => $prefixe.'-'.uniqid().'@selftest.invalid',
            'password_hash' => 'sonde-sans-connexion-possible',
            'role' => $role,
        ]);
    }

    /**
     * Une requete HTTP reelle, par le noyau, avec un jeton d'acces.
     *
     * @param  array<string, mixed>  $corps
     * @return array{statut: int, corps: array<string, mixed>|null}
     */
    private function appelApi(string $methode, string $uri, string $jeton, array $corps = []): array
    {
        // INDISPENSABLE, et trouve en testant la sonde elle-meme : le
        // gestionnaire d'authentification est un singleton du conteneur et
        // MEMORISE l'utilisateur resolu. Sans cet oubli, la deuxieme
        // sous-requete reutilisait le compte de la premiere : l'employeur
        // etait vu comme le candidat, discover/candidates repondait 403 et
        // les deux « Ca m'interesse » etaient poses du meme cote — la sonde
        // rapportait un parcours qui « marchait » sans jamais creer de
        // match. Exactement le genre de fausse assurance qu'un selftest doit
        // rendre impossible.
        Auth::forgetGuards();

        $requete = Request::create($uri, $methode, $methode === 'GET' ? [] : $corps, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$jeton,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], $methode === 'GET' ? null : json_encode($corps));

        $reponse = app(Kernel::class)->handle($requete);
        $decode = json_decode((string) $reponse->getContent(), true);

        return [
            'statut' => $reponse->getStatusCode(),
            'corps' => is_array($decode) ? $decode : null,
        ];
    }

    /**
     * Resume de la pile candidat. On ne renvoie ni titre ni employeur : les
     * offres sont publiques, mais cette reponse-ci n'a aucune raison de les
     * recopier.
     *
     * @param  array{statut: int, corps: array<string, mixed>|null}  $reponse
     * @return array<string, mixed>
     */
    private function resumePile(array $reponse, int $offreAttendue): array
    {
        $jeuncy = $reponse['corps']['data']['jeuncy'] ?? [];
        $ligne = collect(is_array($jeuncy) ? $jeuncy : [])->firstWhere('id', $offreAttendue);

        return [
            'statut' => $reponse['statut'],
            'offres_jeuncy' => is_array($jeuncy) ? count($jeuncy) : 0,
            'offres_partenaires' => count($reponse['corps']['data']['partner']['data'] ?? []),
            'meta' => $reponse['corps']['data']['meta'] ?? null,
            'offre_de_sonde_presente' => $ligne !== null,
            // La distance prouve que la haversine SQL s'execute vraiment sur
            // ce moteur : attendu ~5 km.
            'distance_km_de_la_sonde' => $ligne['distance_km'] ?? null,
            'erreur' => $reponse['corps']['error'] ?? null,
        ];
    }

    /**
     * Resume du deck employeur. Les cartes portent des donnees de vrais
     * candidats : on ne renvoie que des COMPTAGES et la liste des CLES, plus
     * le controle qu'aucune cle interdite n'apparait. Aucune valeur ne sort.
     *
     * @param  array{statut: int, corps: array<string, mixed>|null}  $reponse
     * @return array<string, mixed>
     */
    private function resumeDeck(array $reponse, int $profilAttendu): array
    {
        $cartes = $reponse['corps']['data']['data'] ?? [];
        $cartes = is_array($cartes) ? $cartes : [];

        $interdites = [
            'last_name', 'city', 'postal_code', 'address', 'phone', 'email',
            'birth_date', 'age', 'latitude', 'longitude', 'device_latitude',
            'device_longitude', 'cv_file_url', 'linkedin_url', 'video_url',
            'portfolio_url', 'bio', 'hobbies', 'user_id',
        ];

        $fuites = [];
        foreach ($cartes as $carte) {
            if (! is_array($carte)) {
                continue;
            }
            $fuites = array_merge($fuites, array_values(array_intersect($interdites, array_keys($carte))));
        }

        return [
            'statut' => $reponse['statut'],
            'cartes' => count($cartes),
            'profil_de_sonde_present' => collect($cartes)->firstWhere('id', $profilAttendu) !== null,
            'cles_de_la_premiere_carte' => $cartes === [] ? [] : array_keys((array) $cartes[0]),
            'cles_interdites_trouvees' => $fuites === [] ? 'aucune' : array_values(array_unique($fuites)),
            'erreur' => $reponse['corps']['error'] ?? null,
        ];
    }

    /**
     * Execute l'appel dans une transaction TOUJOURS annulee : la sonde ecrit
     * pour de vrai (c'est le point), mais ne laisse rien derriere elle, meme
     * en cas d'exception.
     */
    private function dansUneTransactionAnnulee(\Closure $appel): mixed
    {
        DB::beginTransaction();

        try {
            return $appel();
        } finally {
            DB::rollBack();
        }
    }

    // Serialise reellement la reponse. json_encode echoue des qu'UNE valeur
    // n'est pas de l'UTF-8 valide, et fait alors tomber la requete entiere en
    // 500 — quel que soit le nombre de lignes correctes.
    private function tailleJson(mixed $payload): string
    {
        $json = json_encode($payload);

        if ($json === false) {
            throw new \RuntimeException('json_encode a echoue : '.json_last_error_msg());
        }

        return strlen($json).' octets de JSON';
    }

    /**
     * Identifiants des profils dont un champ texte n'est pas de l'UTF-8
     * valide. Le texte vient de PDF lus par CvImportService, ou tout octet est
     * possible.
     *
     * Renvoie des IDENTIFIANTS ET DES NOMS DE COLONNES, jamais les valeurs :
     * une valeur mal encodee est une donnee personnelle, et l'inclure ferait
     * d'ailleurs echouer cette reponse-ci de la meme facon.
     */
    private function profilsMalEncodes(): array
    {
        $colonnes = [
            'first_name', 'last_name', 'headline', 'bio', 'city', 'address',
            'hobbies', 'driving_license', 'cv_original_filename',
        ];

        $fautifs = [];

        CandidateProfile::query()
            ->select(array_merge(['id'], $colonnes))
            ->chunkById(100, function ($profils) use ($colonnes, &$fautifs) {
                foreach ($profils as $profil) {
                    foreach ($colonnes as $colonne) {
                        $valeur = $profil->getAttribute($colonne);
                        if (is_string($valeur) && ! mb_check_encoding($valeur, 'UTF-8')) {
                            $fautifs[] = "profil {$profil->id}, colonne {$colonne}";
                        }
                    }
                }
            });

        return $fautifs === [] ? ['aucun profil mal encode'] : $fautifs;
    }

    /**
     * Le resultat de l'appel, ou l'exception decrite sans interrompre les
     * autres essais : un seul appel de cette route doit renvoyer TOUS les
     * diagnostics, pas s'arreter au premier echec.
     */
    private function essai(\Closure $appel): array
    {
        try {
            return ['ok' => true, 'resultat' => $appel()];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'exception' => $e::class,
                // Tronque : un message de QueryException contient la requete
                // SQL complete, inutilement longue ici.
                'message' => mb_substr($e->getMessage(), 0, 500),
                'fichier' => str_replace(base_path().DIRECTORY_SEPARATOR, '', $e->getFile()),
                'ligne' => $e->getLine(),
            ];
        }
    }

    /**
     * Pour une offre donnee, explique candidat par candidat pourquoi il est
     * notifie ou non.
     *
     * POURQUOI CETTE ROUTE. La regle de correspondance a echoue deux fois en
     * production sur des cas qui semblaient evidents, et chaque diagnostic a
     * ete une conjecture : les profils candidats sont derriere une
     * authentification, les journaux inaccessibles sans SSH. Trois
     * allers-retours perdus. Une regle qu'on ne peut pas interroger ne se
     * corrige qu'au hasard.
     *
     * Ne renvoie QUE des identifiants, des booleens et la decision — ni nom,
     * ni email, ni intitule de profil.
     */
    public function matchDebug(string $token, int $jobOffer): Response
    {
        $this->assertAuthorized($token);

        $offre = JobOffer::find($jobOffer);

        // L'effacement doit fonctionner meme quand l'offre n'existe plus :
        // c'est precisement le cas ou ses notifications deviennent des liens
        // morts, et donc celui ou le nettoyage est le plus necessaire.
        if (request()->query('effacer') === '1' && ! $offre) {
            return response()->json([
                'offre' => "Offre {$jobOffer} supprimee — seules ses notifications sont nettoyees.",
                'notifications_effacees' => Notification::query()
                    ->where('type', NotificationType::JOB_OFFER_MATCH->value)
                    ->where('link', '/offres/'.$jobOffer)
                    ->delete(),
                'heure_serveur' => now()->toDateTimeString(),
            ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (! $offre) {
            return response()->json(['erreur' => "Offre {$jobOffer} introuvable."], 404);
        }

        $service = app(JobOfferMatchService::class);
        $r = new \ReflectionClass($service);

        // La regle de correspondance vit desormais dans MatchScorer, avec les
        // memes noms de methodes mais PUBLIQUES (lot 1, contrat §2.2) : on
        // l'appelle directement. Seul isReachable est reste prive dans
        // JobOfferMatchService, d'ou la reflexion conservee pour lui seul.
        $scorer = app(MatchScorer::class);

        $appeler = function (string $methode, array $args) use ($service, $r) {
            $m = $r->getMethod($methode);
            $m->setAccessible(true);

            return $m->invokeArgs($service, $args);
        };

        $motsCles = $scorer->keywordsOf($offre);
        $villeOffre = $scorer->normalize((string) $offre->city);

        $profils = [];

        CandidateProfile::query()
            ->with(['user:id,is_suspended,deleted_account_at', 'skills:id,name', 'software:id,name'])
            ->orderByDesc('id')
            ->limit(60)
            ->get()
            ->each(function (CandidateProfile $profil) use ($offre, $motsCles, $villeOffre, $appeler, $scorer, &$profils) {
                $joignable = $appeler('isReachable', [$profil]);
                $memeVille = $villeOffre !== '' && $scorer->normalize((string) $profil->city) === $villeOffre;
                $motPartage = $scorer->sharesKeyword($profil, $motsCles);
                $contratExclu = $scorer->contractIsExcluded($profil, $offre);

                $profils[] = [
                    'profil' => $profil->id,
                    'joignable' => $joignable,
                    'meme_ville' => $memeVille,
                    'mot_partage' => $motPartage,
                    'contrat_exclu' => $contratExclu,
                    'deja_candidat' => $profil->applications()->where('job_offer_id', $offre->id)->exists(),
                    'deja_notifie' => Notification::where('user_id', $profil->user_id)
                        ->where('link', '/offres/'.$offre->id)->exists(),
                    'NOTIFIE' => $joignable && ! $contratExclu && ($memeVille || $motPartage),
                ];
            });

        // Envoi reel, uniquement sur demande explicite (?envoyer=1).
        //
        // ?profil=N restreint l'envoi a CE seul candidat : indispensable pour
        // tester avec une offre fictive sans arroser de vraies personnes.
        //
        // Volontairement HORS try/catch, contrairement au chemin applicatif
        // (CandidateProfileService avale l'erreur pour ne jamais mettre en
        // peril l'enregistrement d'un profil). C'est ce silence qui a rendu la
        // panne invisible : ici l'exception doit remonter.
        $envoyees = null;
        $profilCible = request()->query('profil');
        $diagnosticCible = null;

        if ($profilCible !== null) {
            $cible = CandidateProfile::find((int) $profilCible);
            $diagnosticCible = $cible
                ? [
                    'profil' => $cible->id,
                    'ville_normalisee' => $scorer->normalize((string) $cible->city),
                    'ville_offre_normalisee' => $villeOffre,
                    'villes_identiques' => $scorer->normalize((string) $cible->city) === $villeOffre,
                    'notifications_existantes' => Notification::where('user_id', $cible->user_id)->count(),
                ]
                : 'PROFIL INTROUVABLE';

            if ($cible && request()->query('envoyer') === '1') {
                $envoyees = $this->essai(fn () => $service->notifyCandidateOfMatchingOffers($cible));
            }
        } elseif (request()->query('envoyer') === '1') {
            // Envoi de masse : exige 'tous=1' EN PLUS.
            //
            // Sans ce second verrou, oublier ?profil=N transformait un essai
            // cible en envoi a tous les candidats correspondants. C'est
            // arrive : 37 personnes prevenues d'une offre de test. Une action
            // visible par des tiers ne doit jamais etre le comportement par
            // defaut d'un parametre omis.
            $envoyees = request()->query('tous') === '1'
                ? $this->essai(fn () => $service->notifyMatchingCandidates($offre))
                : 'REFUSE : precise ?profil=N pour un envoi cible, ou ajoute &tous=1 pour prevenir TOUS les candidats correspondants.';
        }

        // Efface les notifications de correspondance emises pour CETTE offre.
        // Sert a reparer un envoi malencontreux — notamment sur une offre de
        // test, dont le lien deviendrait mort a la suppression de l'offre.
        $effacees = null;
        if (request()->query('effacer') === '1') {
            $effacees = Notification::query()
                ->where('type', NotificationType::JOB_OFFER_MATCH->value)
                ->where('link', '/offres/'.$offre->id)
                ->delete();
        }

        return response()->json([
            'cablage' => [
                'JobOfferService' => $this->cablageMatchService(JobOfferService::class),
                'PaymentService' => $this->cablageMatchService(PaymentService::class),
                'CandidateProfileService' => $this->cablageMatchService(CandidateProfileService::class),
            ],
            'notifications_envoyees_a_l_instant' => $envoyees,
            'notifications_effacees' => $effacees,
            'profil_cible' => $diagnosticCible,
            'offre' => [
                'id' => $offre->id,
                'titre' => $offre->title,
                'ville' => $offre->city,
                'statut' => $offre->status->value,
                'contrat' => $offre->contract_type->value,
                'mots_cles_retenus' => $motsCles,
            ],
            'profils_candidats_en_base' => CandidateProfile::count(),
            'profils' => $profils,
            'heure_serveur' => now()->toDateTimeString(),
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Le service passe recoit-il bien JobOfferMatchService dans son
     * constructeur ?
     *
     * Une version perimee de ces fichiers se construit sans la moindre erreur
     * et n'appelle simplement jamais la notification : la panne est totalement
     * silencieuse, aucune trace nulle part. Ce controle la rend visible.
     */
    private function cablageMatchService(string $classe): string
    {
        if (! class_exists($classe)) {
            return 'CLASSE ABSENTE';
        }

        $constructeur = (new \ReflectionClass($classe))->getConstructor();

        if (! $constructeur) {
            return 'PAS DE CONSTRUCTEUR';
        }

        foreach ($constructeur->getParameters() as $parametre) {
            $type = $parametre->getType();
            if ($type instanceof \ReflectionNamedType && $type->getName() === JobOfferMatchService::class) {
                return 'ok';
            }
        }

        return 'NON CABLE — version perimee sur le serveur';
    }

    // Etat reel des taches planifiees sur le serveur.
    //
    // Une tache cassee ne se voit pas : le cron OVH appelle cron-schedule.php
    // en silence, et si bootstrap/app.php n'a pas ete redeploye (il ne fait
    // partie d'aucun dossier qu'on envoie habituellement) ou si le fichier de
    // commande manque, rien ne s'execute et personne ne l'apprend — les
    // rappels de visio ne partent simplement jamais. Constate le 2026-08-17 :
    // impossible de savoir de l'exterieur si video-rooms:send-reminders etait
    // reellement planifie en production.
    //
    // Ce endpoint repond a deux questions distinctes, et c'est leur croisement
    // qui compte : la commande est-elle ENREGISTREE (le fichier existe), et
    // est-elle PLANIFIEE (bootstrap/app.php a jour) ? Une commande enregistree
    // mais non planifiee ne tournera jamais ; une commande planifiee mais non
    // enregistree fait echouer schedule:run en entier, donc aussi les autres
    // taches.
    public function scheduler(string $token): Response
    {
        $this->assertAuthorized($token);

        // Passer par schedule:list plutot que de lire app(Schedule::class)
        // directement : withSchedule() s'accroche a Artisan::starting(), donc
        // le planificateur est VIDE tant que la console n'a pas demarre. Lu
        // depuis une requete web, il aurait toujours paru vide et ce controle
        // aurait annonce "non planifiee" meme quand tout fonctionne — piege
        // rencontre en ecrivant ce endpoint. Artisan::call() demarre la
        // console, donc declenche le peuplement.
        Artisan::call('schedule:list');
        $sortie = Artisan::output();

        $planifiees = collect(explode("\n", $sortie))
            ->map(fn (string $ligne) => trim($ligne))
            ->filter()
            ->all();

        return response()->json([
            'taches' => self::comparerTaches(array_keys(Artisan::all()), $planifiees),
            // Ce que schedule:list ne dira jamais : le cron tourne-t-il ?
            'cron' => self::verdictBattement(Cache::get(self::CLE_BATTEMENT), now()),
            // ... et ce que le battement lui-meme ne dit pas : chaque tache
            // tourne-t-elle ? Un cron qui passe n'a jamais garanti qu'une
            // tache s'execute, c'est toute la lecon du 2026-09-10.
            'dernieres_passes' => self::verdictPasses(self::marqueursDePasse(), now()),
            // Rapport du dernier import La bonne alternance : combien lues,
            // retenues, exclues et pourquoi. Un import qui echoue chaque nuit
            // serait invisible sans ceci.
            'import_lba' => LbaImportService::lastReport(),
            'sortie_brute' => $sortie,
            'heure_serveur' => now()->toDateTimeString(),
        ]);
    }

    // Extrait du controleur pour la meme raison que comparerTaches : on ne
    // peut pas faire taire un vrai cron depuis un test HTTP, alors que
    // c'est justement le cas degrade a couvrir.
    public static function verdictBattement(?string $dernierPassage, \DateTimeInterface $maintenant): array
    {
        if ($dernierPassage === null) {
            return [
                'dernier_passage' => null,
                'il_y_a_minutes' => null,
                'etat' => 'AUCUN BATTEMENT — soit le cron ne tourne pas, soit ce battement vient d\'etre deploye et la prochaine heure n\'est pas passee.',
            ];
        }

        // Calcul sur les horodatages plutot que diffInMinutes : le signe de
        // cette methode a change entre versions de Carbon, et un signe
        // inverse ferait dire "tout va bien" a un cron arrete.
        $minutes = (int) floor(($maintenant->getTimestamp() - strtotime($dernierPassage)) / 60);

        return [
            'dernier_passage' => $dernierPassage,
            'il_y_a_minutes' => $minutes,
            'etat' => $minutes <= self::BATTEMENT_TOLERANCE_MINUTES
                ? 'ok — le cron OVH tourne'
                : "CRON MUET depuis {$minutes} minutes — plus aucune tache planifiee ne s'execute",
        ];
    }

    /** @return array<string, ?string> */
    private static function marqueursDePasse(): array
    {
        $marqueurs = [];
        foreach (array_keys(self::TACHES_A_MARQUEUR) as $commande) {
            $marqueurs[$commande] = Cache::get(self::CLE_PASSE_PREFIXE.$commande);
        }

        return $marqueurs;
    }

    // Extrait du controleur pour la meme raison que comparerTaches et
    // verdictBattement : on ne peut pas faire vieillir un vrai marqueur depuis
    // un test HTTP, alors que le cas a couvrir est justement celui d'une tache
    // qui a cesse de passer.
    //
    // Tolerances. Une tache quotidienne s'execute au premier passage du cron
    // apres minuit : la voir dater d'hier est normal en pleine nuit, la voir
    // dater d'avant-hier ne l'est plus. Une hebdomadaire part au premier
    // passage du lundi, donc son marqueur a au plus six jours.
    public static function verdictPasses(array $marqueurs, \DateTimeInterface $maintenant): array
    {
        $aujourdhui = strtotime($maintenant->format('Y-m-d'));
        $rapport = [];

        foreach (self::TACHES_A_MARQUEUR as $commande => $periode) {
            $marqueur = $marqueurs[$commande] ?? null;

            if (! $marqueur) {
                $rapport[$commande] = [
                    'derniere_passe' => null,
                    'etat' => "JAMAIS PASSEE — soit le correctif du planificateur vient d'etre deploye, soit cette tache ne s'execute pas.",
                ];

                continue;
            }

            $jours = (int) floor(($aujourdhui - strtotime($marqueur)) / 86400);
            $limite = $periode === 'semaine' ? 7 : 1;

            $rapport[$commande] = [
                'derniere_passe' => $marqueur,
                'il_y_a_jours' => $jours,
                'etat' => $jours <= $limite
                    ? 'ok'
                    : "EN RETARD — {$jours} jours sans passage, cadence attendue : une fois par {$periode}",
            ];
        }

        return $rapport;
    }

    // Taches que l'application est censee executer. Toute nouvelle commande
    // planifiee doit etre ajoutee ici, sinon son absence en production passera
    // inapercue — c'est exactement le scenario que ce controle previent.
    public const TACHES_ATTENDUES = [
        'job-offers:expire',
        'job-offers:archive-expired-trials',
        'cvs:archive-inactive',
        'lba:import',
        'video-rooms:send-reminders',
        'cv-downloads:purge',
        'job-offers:notify-matching-candidates',
        // Rattrapage du geocodage (lot 1 du match) : sans elle, une ligne
        // creee pendant une panne de l'IGN reste hors des deux piles
        // indefiniment, sans qu'aucune erreur ne le signale.
        'geocode:backfill',
    ];

    // Extrait du controleur pour etre testable sur des entrees choisies : le
    // planificateur est peuple au demarrage de Laravel et ne peut pas etre vide
    // proprement depuis un test HTTP, or c'est justement le cas degrade qu'il
    // faut couvrir.
    public static function comparerTaches(array $enregistrees, array $planifiees): array
    {
        $rapport = [];

        foreach (self::TACHES_ATTENDUES as $commande) {
            $estEnregistree = in_array($commande, $enregistrees, true);
            $estPlanifiee = (bool) collect($planifiees)->first(fn (string $c) => str_contains($c, $commande));

            $rapport[$commande] = match (true) {
                $estEnregistree && $estPlanifiee => 'ok',
                ! $estEnregistree && $estPlanifiee => 'PLANIFIEE MAIS FICHIER DE COMMANDE ABSENT — casse tout schedule:run',
                $estEnregistree && ! $estPlanifiee => 'presente mais NON PLANIFIEE — ne tournera jamais (bootstrap/app.php a redeployer)',
                default => 'ABSENTE des deux cotes',
            };
        }

        return $rapport;
    }

    // Verifie la presence (pas la valeur) des variables d'environnement dont
    // depend une fonctionnalite reelle de l'app. Sert a detecter en 5 secondes
    // un .env de staging/prod desynchronise du .env local (ex: cle Resend
    // jamais copiee sur le serveur, oubliee lors d'un ajout de config) sans
    // avoir a ouvrir le fichier a la main via WinSCP - pas d'acces SSH sur cet
    // hebergement pour verifier autrement.
    public function envCheck(string $token): Response
    {
        $this->assertAuthorized($token);

        $keys = [
            'APP_URL',
            'FRONTEND_URL',
            'JWT_SECRET',
            'JWT_REFRESH_SECRET',
            'GOOGLE_CLIENT_ID',
            'GOOGLE_CLIENT_SECRET',
            'GOOGLE_REDIRECT_URI',
            'STRIPE_SECRET_KEY',
            'STRIPE_WEBHOOK_SECRET',
            'STRIPE_COMPANY_OFFER_PRICE_CENTS',
            'STRIPE_CFA_OFFER_PRICE_CENTS',
            'STRIPE_COMPANY_SUBSCRIPTION_PRICE_CENTS',
            'STRIPE_CFA_SUBSCRIPTION_PRICE_CENTS',
            'RESEND_API_KEY',
            'RESEND_FROM_EMAIL',
        ];

        $report = collect($keys)
            ->mapWithKeys(fn (string $key) => [$key => filled(env($key)) ? 'ok' : 'MANQUANT'])
            ->all();

        // Les tarifs ne se contentent pas d'etre presents : ils doivent avoir la
        // BONNE valeur. Un .env de prod reste sur ses anciens montants apres une
        // hausse de tarif (la valeur par defaut du config n'entre en jeu que si
        // la ligne est absente, pas si elle est presente et perimee) — c'est
        // ainsi qu'on facturerait 79€ au lieu de 499€ sans s'en apercevoir.
        $report['_montants_factures'] = [
            'abonnement_entreprise' => config('services.stripe.company_subscription_price_cents').' centimes',
            'abonnement_cfa' => config('services.stripe.cfa_subscription_price_cents').' centimes',
            'tarif_fondateur' => config('services.stripe.founder_subscription_price_cents').' centimes',
            'places_fondateur' => config('services.stripe.founder_seats_total'),
            'offre_entreprise' => config('services.stripe.company_offer_price_cents').' centimes',
            'offre_cfa' => config('services.stripe.cfa_offer_price_cents').' centimes',
        ];

        // Modele gratuit et import La bonne alternance (2026-09-15). La cle
        // n'est jamais affichee ; les autres valeurs le sont, elles pilotent
        // ce que les candidats verront.
        $report['_jeuncy'] = [
            'gratuit' => (bool) config('services.jeuncy.gratuit'),
            'inscription_cfa_ouverte' => (bool) config('services.jeuncy.inscription_cfa_ouverte'),
        ];
        $report['_lba'] = [
            'LBA_API_KEY' => filled(config('services.lba.api_key')) ? 'ok' : 'MANQUANTE — import desactive',
            'departements' => config('services.lba.departements'),
            'siret_whitelist' => config('services.lba.siret_whitelist'),
            'mesure_seulement' => (bool) config('services.lba.mesure_seulement'),
            'import_demande' => Cache::get(self::CLE_IMPORT_LBA_DEMANDE),
            'dernier_import' => LbaImportService::lastReport(),
        ];

        return response()->json($report);
    }

    // Demande un import La bonne alternance au prochain passage du cron
    // (toutes les heures a :41), sans attendre 4h du matin. L'import lui-meme
    // ne tourne PAS ici : telecharger et lire des centaines de Mo dans une
    // requete HTTP depasserait le temps d'execution autorise par l'hebergeur.
    // Le mode (mesure ou publication) reste celui du .env.
    public function lbaImport(string $token): Response
    {
        $this->assertAuthorized($token);

        if (request()->query('maintenant') === '1') {
            Cache::forever(self::CLE_IMPORT_LBA_DEMANDE, now()->toDateTimeString());
        }
        if (request()->query('annuler') === '1') {
            Cache::forget(self::CLE_IMPORT_LBA_DEMANDE);
        }

        // ?executer=1 : import IMMEDIAT, dans cette requete. Mesure le
        // 2026-09-16 : 22 s pour 576 Mo, bien en deca du temps accorde par
        // l'hebergeur. La limite est relevee par precaution ; si un jour le
        // fichier grossit au point de depasser, ?maintenant=1 (via le cron)
        // reste la voie sure.
        if (request()->query('executer') === '1') {
            @set_time_limit(900);
            $debut = microtime(true);
            $code = Artisan::call('lba:import');

            return response()->json([
                'execution' => $code === 0 ? 'ok' : "ECHEC (code {$code})",
                'duree_totale_s' => (int) round(microtime(true) - $debut),
                'sortie' => trim(Artisan::output()),
                'rapport' => LbaImportService::lastReport(),
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // ?apercu=1 : les premiers octets de l'export, pour verifier que le
        // lecteur cherche au bon endroit (le 2026-09-16, 576 Mo recus et zero
        // offre lue : la structure du fichier n'etait pas celle supposee).
        if (request()->query('apercu') === '1') {
            try {
                $apercu = app(LbaClient::class)->exportPreview();
            } catch (\Throwable $e) {
                $apercu = 'ERREUR : '.$e->getMessage();
            }

            return response()->json(['apercu_export' => $apercu], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $prochainPassage = now()->minute < 41 ? now()->setTime(now()->hour, 41) : now()->addHour()->setTime(now()->addHour()->hour, 41);

        return response()->json([
            'cle_api' => filled(config('services.lba.api_key')) ? 'ok' : 'MANQUANTE — l\'import se terminera sans rien faire',
            'mesure_seulement' => (bool) config('services.lba.mesure_seulement'),
            'import_demande' => Cache::get(self::CLE_IMPORT_LBA_DEMANDE),
            'prochain_passage_cron_estime' => $prochainPassage->toDateTimeString().' (heure serveur, UTC)',
            'dernier_import' => LbaImportService::lastReport(),
            'aide' => '?maintenant=1 pour demander un import au prochain passage du cron, ?annuler=1 pour retirer la demande. Le resultat apparait ici et dans /admin (Offres partenaires).',
        ]);
    }

    /**
     * Rattrapage du geocodage depuis le navigateur (pas de SSH sur OVH).
     *
     * Sans elle, la seule facon de geocoder les 115 profils, l'offre d'IDA et
     * les organisations existantes serait d'attendre le passage nocturne du
     * cron — et de ne rien pouvoir constater en attendant. Or c'est
     * precisement le premier geste apres la migration : tant qu'une ligne n'a
     * pas de coordonnees, elle n'entre dans aucune pile.
     *
     * Par defaut la route ne fait RIEN et se contente de compter ce qui
     * reste a faire : une commande qui interroge un service externe des
     * centaines de fois ne doit pas partir sur une simple visite d'URL.
     * ?executer=1 lance la passe, ?chunk=N regle la taille des lots,
     * ?only=profiles|offers|organizations la restreint. La commande est
     * idempotente : elle ne reprend que les lignes avec code postal et sans
     * coordonnees, donc on peut la relancer autant de fois que necessaire —
     * ce qui est le mode d'emploi quand le temps d'execution de PHP coupe la
     * passe en cours de route.
     */
    public function geocodeBackfill(string $token): Response
    {
        $this->assertAuthorized($token);

        $restant = fn (string $table) => DB::table($table)
            ->whereNotNull('postal_code')
            ->where('postal_code', '!=', '')
            ->where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'))
            ->count();

        $aFaire = [
            'profils' => $restant('candidate_profiles'),
            'offres' => $restant('job_offers'),
            'entreprises' => $restant('companies'),
            'cfa' => $restant('cfa_organizations'),
            'communes_en_cache' => DB::table('geocode_cache')->count(),
        ];

        if (request()->query('executer') !== '1') {
            return response()->json([
                'a_geocoder' => $aFaire,
                'aide' => 'Ajouter ?executer=1 pour lancer la passe. Options : &chunk=100 (taille des lots), &only=profiles|offers|organizations. Idempotente : relancer ne refait que ce qui reste.',
            ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        // Le geocodeur repond en quelques centaines de millisecondes par
        // commune inconnue, et rien du tout pour une commune deja en cache :
        // la premiere passe est la seule longue. La limite est relevee comme
        // pour l'import LBA, et le decoupage en lots permet de reprendre.
        @set_time_limit(900);
        $debut = microtime(true);

        $options = ['--chunk' => (int) (request()->query('chunk') ?: 100)];
        if (request()->query('only')) {
            $options['--only'] = (string) request()->query('only');
        }

        $code = Artisan::call('geocode:backfill', $options);

        return response()->json([
            'execution' => $code === 0 ? 'ok' : "ECHEC (code {$code})",
            'duree_s' => (int) round(microtime(true) - $debut),
            'sortie' => trim(Artisan::output()),
            'avant' => $aFaire,
            'apres' => [
                'profils' => $restant('candidate_profiles'),
                'offres' => $restant('job_offers'),
                'entreprises' => $restant('companies'),
                'cfa' => $restant('cfa_organizations'),
                'communes_en_cache' => DB::table('geocode_cache')->count(),
            ],
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    // ------------------------------------------------------------------
    // Journal d'erreurs (deploy-tools-30, 2026-09-23)
    //
    // Des etudiants utilisent Jeuncy et remontent des bugs a Pierre. Sans
    // acces SSH ni phpMyAdmin utile ici, je n'avais aucun moyen de savoir si
    // une action echouait cote serveur ou cote navigateur : chaque hypothese
    // coutait un aller-retour FTP. Cette sonde lit la fin du journal Laravel
    // et ne renvoie que les entrees d'erreur.
    //
    // Donnees personnelles : les adresses email et les jetons sont masques
    // avant l'envoi, et la trace est coupee aux premieres lignes — c'est la
    // ligne d'erreur qui situe la panne, pas les quarante appels de Laravel.
    // ------------------------------------------------------------------
    public function logs(string $token): Response
    {
        $this->assertAuthorized($token);

        $dossier = storage_path('logs');
        $fichiers = glob($dossier.'/*.log') ?: [];
        if ($fichiers === []) {
            return response()->json([
                'journal' => 'aucun fichier dans storage/logs',
                'indice' => 'Un journal vide peut aussi vouloir dire que storage/logs n\'est pas inscriptible : verifier les droits du dossier.',
            ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        usort($fichiers, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $fichier = $fichiers[0];

        $maxEntrees = max(1, min(100, (int) (request()->query('entrees') ?: 20)));
        $contient = trim((string) request()->query('contient'));

        // Lecture de la fin du fichier seulement : un journal de production
        // peut peser des dizaines de Mo, et file() les chargerait en memoire.
        $octets = min(filesize($fichier) ?: 0, 600_000);
        $handle = fopen($fichier, 'rb');
        if ($handle === false) {
            return response()->json(['journal' => 'illisible : '.basename($fichier)], 200);
        }
        fseek($handle, -$octets, SEEK_END);
        $queue = (string) fread($handle, $octets);
        fclose($handle);

        // Une entree commence par un horodatage entre crochets ; les lignes
        // suivantes (trace) lui appartiennent.
        $morceaux = preg_split('/\n(?=\[\d{4}-\d{2}-\d{2})/', $queue) ?: [];
        $entrees = [];
        foreach (array_reverse($morceaux) as $entree) {
            if (! preg_match('/\.(ERROR|CRITICAL|ALERT|EMERGENCY):/', $entree)) {
                continue;
            }
            if ($contient !== '' && stripos($entree, $contient) === false) {
                continue;
            }

            $lignes = array_slice(preg_split('/\r?\n/', trim($entree)) ?: [], 0, 4);
            $entrees[] = $this->masquerDonneesPersonnelles(implode("\n", $lignes));

            if (count($entrees) >= $maxEntrees) {
                break;
            }
        }

        return response()->json([
            'fichier' => basename($fichier),
            'taille_octets' => filesize($fichier),
            'derniere_ecriture' => date('Y-m-d H:i:s', filemtime($fichier) ?: 0),
            'erreurs_trouvees' => count($entrees),
            'erreurs' => $entrees,
            'aide' => 'Options : ?entrees=50 (defaut 20, max 100), &contient=skills (filtre texte).',
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    // Emails, jetons Bearer et mots de passe eventuels : une trace d'exception
    // en contient plus souvent qu'on ne le croit (parametres de requete
    // inclus dans certains messages).
    private function masquerDonneesPersonnelles(string $texte): string
    {
        $texte = preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/', '***@***', $texte) ?? $texte;
        $texte = preg_replace('/(Bearer|token|password|secret)[=:\s"\']+[\w.\-]{6,}/i', '$1=***', $texte) ?? $texte;

        return $texte;
    }

    // ------------------------------------------------------------------
    // Code postal d'une offre (deploy-tools-30)
    //
    // Une offre sans code postal n'a pas de coordonnees, donc n'entre pas
    // dans le deck du candidat (JOB_OFFER_NOT_LOCATED). C'est le cas de
    // l'offre d'IDA depuis le lot 1 : le champ n'existait pas quand elle a
    // ete publiee, et son proprietaire est un compte CFA auquel je n'ai pas
    // acces. Ecrit le code postal, puis geocode l'offre dans la foulee.
    // ------------------------------------------------------------------
    public function offerPostalCode(string $token, int $jobOffer): Response
    {
        $this->assertAuthorized($token);

        $offre = JobOffer::find($jobOffer);
        if (! $offre) {
            return response()->json(['erreur' => "Aucune offre #{$jobOffer}."], 404);
        }

        $avant = [
            'titre' => $offre->title,
            'ville' => $offre->city,
            'code_postal' => $offre->postal_code,
            'latitude' => $offre->latitude,
            'longitude' => $offre->longitude,
        ];

        $cp = trim((string) request()->query('cp'));
        if ($cp === '') {
            return response()->json([
                'offre' => $avant,
                'aide' => 'Ajouter ?cp=66000 pour ecrire le code postal et geocoder l\'offre.',
            ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (! preg_match('/^\d{5}$/', $cp)) {
            return response()->json(['erreur' => 'Code postal attendu : cinq chiffres.'], 400);
        }

        $offre->postal_code = $cp;
        // Les coordonnees actuelles (nulles ou calculees sur un autre code
        // postal) ne valent plus rien : on les efface pour que le geocodage
        // reprenne l'offre.
        $offre->latitude = null;
        $offre->longitude = null;
        $offre->save();

        Artisan::call('geocode:backfill', ['--only' => 'offers']);
        $offre->refresh();

        return response()->json([
            'execution' => 'ok',
            'avant' => $avant,
            'apres' => [
                'titre' => $offre->title,
                'ville' => $offre->city,
                'code_postal' => $offre->postal_code,
                'latitude' => $offre->latitude,
                'longitude' => $offre->longitude,
            ],
            'geocodage' => trim(Artisan::output()),
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
