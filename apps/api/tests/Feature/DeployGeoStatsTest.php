<?php

namespace Tests\Feature;

use App\Enums\ContractType;
use App\Enums\ExternalJobOfferStatus;
use App\Enums\JobOfferStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\DeployController;
use App\Models\CandidateProfile;
use App\Models\CfaOrganization;
use App\Models\Company;
use App\Models\ExternalJobOffer;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

// Sonde « geo-stats » (/deploy/{token}/status?geo=1) : les comptages qui
// dimensionnent le match mobile avant de le concevoir. Ce que ces tests
// garantissent : la structure de la reponse, l'exactitude des regroupements
// par departement, l'exclusion des comptes supprimes/suspendus, et surtout
// qu'AUCUNE donnee personnelle ne sort — la sonde lit des profils reels.
class DeployGeoStatsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'secret-token';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.deploy_token', self::TOKEN);
    }

    private function sonde(): TestResponse
    {
        return $this->get('/deploy/'.self::TOKEN.'/status?geo=1');
    }

    // Memes cles, memes effectifs, sans presumer de l'ordre : entre deux
    // departements de meme effectif, l'ordre depend du GROUP BY du moteur.
    private function assertMemesComptages(array $attendu, array $reel): void
    {
        ksort($attendu);
        ksort($reel);

        $this->assertSame($attendu, $reel);
    }

    private function candidat(array $profil = [], array $compte = []): CandidateProfile
    {
        static $n = 0;
        $n++;

        $user = User::create(array_merge([
            'email' => "candidat-{$n}@example.com",
            'password_hash' => 'x',
            'role' => UserRole::CANDIDATE,
        ], $compte));

        return CandidateProfile::create(array_merge([
            'user_id' => $user->id,
            'first_name' => 'Léa',
            'last_name' => 'Girard',
        ], $profil));
    }

    // SQLite n'a pas de fonctions trigonometriques : on lui prete celles de
    // PHP, pour que la haversine ecrite en SQL s'execute reellement dans les
    // tests, avec les memes noms qu'elle appelle sur MySQL.
    private function activerTrigonometrieSqlite(): void
    {
        $pdo = DB::connection()->getPdo();

        foreach (['sin' => 'sin', 'cos' => 'cos', 'asin' => 'asin', 'sqrt' => 'sqrt', 'radians' => 'deg2rad'] as $nom => $php) {
            $pdo->sqliteCreateFunction($nom, $php, 1);
        }
        $pdo->sqliteCreateFunction('power', 'pow', 2);
    }

    private function offrePartenaire(array $overrides = []): ExternalJobOffer
    {
        static $n = 0;
        $n++;

        return ExternalJobOffer::create(array_merge([
            'source' => ExternalJobOffer::SOURCE_LBA,
            'external_key' => "lba-{$n}",
            'title' => "Offre partenaire {$n}",
            'description' => 'Missions.',
            'apply_url' => 'https://labonnealternance.apprentissage.beta.gouv.fr/emploi/'.$n,
            'status' => ExternalJobOfferStatus::ACTIVE,
            'import_batch' => 'lot-test',
            'last_seen_at' => now(),
        ], $overrides));
    }

    // ------------------------------------------------------------------
    // Garde d'acces : meme jeton que le reste des outils de deploiement.
    // ------------------------------------------------------------------

    public function test_geo_stats_require_a_valid_deploy_token(): void
    {
        $this->get('/deploy/wrong-token/status?geo=1')->assertNotFound();
    }

    public function test_geo_stats_are_inert_when_no_deploy_token_configured(): void
    {
        Config::set('app.deploy_token', null);

        $this->get('/deploy/anything/status?geo=1')->assertNotFound();
    }

    // La greffe sur /status ne doit pas changer son comportement d'origine :
    // sans ?geo=1, c'est toujours migrate:status en texte brut.
    public function test_status_without_geo_still_returns_the_migration_status_as_text(): void
    {
        $response = $this->get('/deploy/'.self::TOKEN.'/status');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('create_users_table', false);
    }

    // ------------------------------------------------------------------
    // Structure et exactitude des comptages
    // ------------------------------------------------------------------

    public function test_geo_stats_have_the_expected_structure_and_a_duration(): void
    {
        $response = $this->sonde();

        $response->assertOk();
        $response->assertJsonPath('version_outils_deploiement', DeployController::DEPLOY_TOOLS_VERSION);
        $response->assertJsonStructure([
            'candidats' => [
                'total', 'exclus_supprimes_ou_suspendus', 'par_departement', 'departements_regroupes_sous_autres',
                'code_postal_vide_mais_ville_renseignee', 'code_postal_renseigne_mais_inexploitable',
                'visibles_en_cvtheque', 'avec_date_de_naissance', 'moins_de_16_ans',
                'avec_permis', 'avec_photo', 'avec_cv_depose',
            ],
            'offres_jeuncy' => ['par_statut', 'publiees_par_departement', 'publiees_sans_code_postal_exploitable'],
            'offres_partenaires' => ['total', 'sans_coordonnees', 'dans_le_66', 'autour_de_perpignan', 'top_10_departements'],
            'organisations' => [
                'entreprises' => ['total', 'avec_siret', 'sans_siret', 'publiques'],
                'cfa' => ['total'],
            ],
            'serveur' => [
                'php',
                'base_de_donnees' => ['pilote', 'version', 'st_distance_sphere' => ['disponible']],
                'extensions' => ['gd', 'imagick'],
                'php_ini' => ['memory_limit', 'upload_max_filesize', 'post_max_size', 'max_execution_time', 'max_input_time'],
                'queue_default', 'cache_default',
                'secrets_presents' => ['RESEND_API_KEY', 'LBA_API_KEY', 'JWT_SECRET'],
                'fuseau_horaire', 'heure_serveur',
            ],
            'duree_ms',
            'heure_serveur',
        ]);
        $this->assertIsInt($response->json('duree_ms'));
        $this->assertSame('sqlite', $response->json('serveur.base_de_donnees.pilote'));
        $this->assertIsBool($response->json('serveur.secrets_presents.JWT_SECRET'));
    }

    public function test_candidates_are_counted_by_department_and_by_attribute(): void
    {
        $this->candidat([
            'postal_code' => '66000', 'city' => 'Perpignan',
            'birth_date' => now()->subYears(15)->toDateString(), // moins de 16 ans
            'driving_license' => 'B', 'photo_url' => '/storage/photos/a.jpg',
            'cv_file_url' => '/storage/cvs/a.pdf',
        ]);
        $this->candidat([
            'postal_code' => ' F-66100 ', 'city' => 'Perpignan', // saisie sale, exploitable
            'birth_date' => now()->subYears(17)->toDateString(),
            'is_visible_in_cvtheque' => false,
        ]);
        $this->candidat(['postal_code' => '66200', 'city' => 'Elne']);
        $this->candidat(['postal_code' => '31000', 'city' => 'Toulouse']); // seul dans le 31 : « autres »
        $this->candidat(['postal_code' => null, 'city' => 'Narbonne']);   // vide mais ville
        $this->candidat(['postal_code' => 'Perpignan', 'city' => null]);  // renseigne, illisible

        $candidats = $this->sonde()->assertOk()->json('candidats');

        $this->assertSame(6, $candidats['total']);
        $this->assertSame(0, $candidats['exclus_supprimes_ou_suspendus']);
        $this->assertMemesComptages(['66' => 3, 'inconnu' => 2, 'autres' => 1], $candidats['par_departement']);
        $this->assertSame(1, $candidats['departements_regroupes_sous_autres']);
        $this->assertSame(1, $candidats['code_postal_vide_mais_ville_renseignee']);
        $this->assertSame(1, $candidats['code_postal_renseigne_mais_inexploitable']);
        $this->assertSame(5, $candidats['visibles_en_cvtheque']);
        $this->assertSame(2, $candidats['avec_date_de_naissance']);
        $this->assertSame(1, $candidats['moins_de_16_ans']);
        $this->assertSame(1, $candidats['avec_permis']);
        $this->assertSame(1, $candidats['avec_photo']);
        $this->assertSame(1, $candidats['avec_cv_depose']);
    }

    // Un jeune qui a 16 ans aujourd'hui n'est PAS « moins de 16 ans » : la
    // borne doit etre exacte au jour, c'est la regle serveur du match.
    public function test_a_candidate_turning_sixteen_today_is_not_under_sixteen(): void
    {
        $this->candidat(['birth_date' => now()->subYears(16)->toDateString()]);
        $this->candidat(['birth_date' => now()->subYears(16)->addDay()->toDateString()]);

        $this->assertSame(1, $this->sonde()->json('candidats.moins_de_16_ans'));
    }

    // Comptes supprimes et suspendus : hors du match, donc hors des
    // comptages — mais comptes a part, pour que total + exclus reste
    // rapprochable du nombre de profils en base.
    public function test_suspended_and_deleted_accounts_are_excluded_but_counted(): void
    {
        $this->candidat(['postal_code' => '66000']);
        $this->candidat(['postal_code' => '66000']);
        $this->candidat(['postal_code' => '66000']);
        $this->candidat(['postal_code' => '75001'], ['is_suspended' => true]);
        $this->candidat(['postal_code' => '13001'], ['deleted_account_at' => now()]);

        $candidats = $this->sonde()->json('candidats');

        $this->assertSame(3, $candidats['total']);
        $this->assertSame(2, $candidats['exclus_supprimes_ou_suspendus']);
        $this->assertMemesComptages(['66' => 3], $candidats['par_departement']);
        $this->assertSame(0, $candidats['departements_regroupes_sous_autres']);
    }

    // Secret statistique : un departement de moins de trois candidats ne
    // sort jamais nomme, il est fondu dans « autres ». « inconnu » n'est pas
    // un departement (c'est une mesure de qualite de la donnee) et reste
    // visible meme a 1.
    public function test_departments_with_fewer_than_three_candidates_are_merged_into_others(): void
    {
        foreach (['66000', '66000', '66100'] as $codePostal) {
            $this->candidat(['postal_code' => $codePostal]);
        }
        $this->candidat(['postal_code' => '11100']);
        $this->candidat(['postal_code' => '11100']);
        $this->candidat(['postal_code' => '48000']);
        $this->candidat(['postal_code' => 'n/a']);

        $candidats = $this->sonde()->json('candidats');

        $this->assertMemesComptages(['66' => 3, 'autres' => 3, 'inconnu' => 1], $candidats['par_departement']);
        $this->assertSame(2, $candidats['departements_regroupes_sous_autres']);
        $this->assertArrayNotHasKey('11', $candidats['par_departement']);
        $this->assertArrayNotHasKey('48', $candidats['par_departement']);
    }

    public function test_jeuncy_offers_are_counted_by_status_and_by_owner_department(): void
    {
        $rh = User::create(['email' => 'rh@nexatech.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
        $entreprise = Company::create(['user_id' => $rh->id, 'name' => 'NexaTech', 'postal_code' => '66100', 'siret' => '73282932000074']);
        $direction = User::create(['email' => 'direction@ida.example.com', 'password_hash' => 'x', 'role' => UserRole::CFA]);
        $cfa = CfaOrganization::create(['user_id' => $direction->id, 'name' => 'IDA']); // sans code postal

        $offre = fn (array $o) => JobOffer::create(array_merge([
            'title' => 'Vendeur', 'description' => 'Accueil.',
            'contract_type' => ContractType::ALTERNANCE,
            'payment_status' => PaymentStatus::PENDING,
        ], $o));
        $offre(['company_id' => $entreprise->id, 'status' => JobOfferStatus::PUBLISHED]);
        $offre(['company_id' => $entreprise->id, 'status' => JobOfferStatus::PUBLISHED]);
        $offre(['company_id' => $entreprise->id, 'status' => JobOfferStatus::DRAFT]);
        $offre(['cfa_organization_id' => $cfa->id, 'status' => JobOfferStatus::PUBLISHED]);

        $reponse = $this->sonde();
        $offres = $reponse->json('offres_jeuncy');

        $this->assertMemesComptages(['DRAFT' => 1, 'PUBLISHED' => 3], $offres['par_statut']);
        $this->assertMemesComptages(['66' => 2, 'inconnu' => 1], $offres['publiees_par_departement']);
        $this->assertSame(1, $offres['publiees_sans_code_postal_exploitable']);

        $organisations = $reponse->json('organisations');
        $this->assertSame(['total' => 1, 'avec_siret' => 1, 'sans_siret' => 0, 'publiques' => 1], $organisations['entreprises']);
        $this->assertSame(['total' => 1], $organisations['cfa']);
    }

    public function test_partner_offers_are_counted_with_and_without_coordinates(): void
    {
        $this->offrePartenaire(['department' => '66', 'latitude' => 42.6986, 'longitude' => 2.8956]);
        $this->offrePartenaire(['department' => '66', 'latitude' => null, 'longitude' => null]);
        $this->offrePartenaire(['department' => '31', 'latitude' => 43.6045, 'longitude' => 1.4440]);
        $this->offrePartenaire(['department' => '66', 'status' => ExternalJobOfferStatus::EXCLUDED, 'latitude' => 42.7, 'longitude' => 2.9]);

        $partenaires = $this->sonde()->json('offres_partenaires');

        $this->assertSame(3, $partenaires['total']);
        $this->assertSame(1, $partenaires['sans_coordonnees']);
        $this->assertSame(2, $partenaires['dans_le_66']);
        $this->assertSame(['66' => 2, '31' => 1], $partenaires['top_10_departements']);

        // SQLite n'a pas les fonctions trigonometriques : la sonde doit le
        // dire, pas planter. Sur MySQL, le meme bloc renvoie les rayons
        // (verifies par le test suivant, qui prete a SQLite les fonctions
        // de PHP).
        $autour = $partenaires['autour_de_perpignan'];
        if (is_string($autour)) {
            $this->assertStringStartsWith('indisponible', $autour);
        } else {
            $this->assertSame(['10_km' => 1, '30_km' => 1, '50_km' => 1, '100_km' => 1], $autour);
        }
    }

    // La haversine en SQL, verifiee sur des distances connues. Narbonne est a
    // 55,8 km de Perpignan (calcul a la main : R = 6371 km, degres convertis
    // en radians, d = 2R.asin(sqrt(sin2(dlat/2) + cos(lat1).cos(lat2).sin2(dlon/2)))),
    // donc comptee a 100 km mais pas a 50 ; Toulouse (155,6 km) jamais. Une
    // offre sans coordonnees ou non active n'entre dans aucun rayon.
    public function test_partner_offers_are_counted_within_each_radius_around_perpignan(): void
    {
        $this->activerTrigonometrieSqlite();

        $this->offrePartenaire(['department' => '66', 'latitude' => 42.6887, 'longitude' => 2.8948]); // Perpignan, 0 km
        $this->offrePartenaire(['department' => '11', 'latitude' => 43.1839, 'longitude' => 3.0042]); // Narbonne, 55,8 km
        $this->offrePartenaire(['department' => '31', 'latitude' => 43.6045, 'longitude' => 1.4440]); // Toulouse, 155,6 km
        $this->offrePartenaire(['department' => '66', 'latitude' => null, 'longitude' => null]);
        $this->offrePartenaire(['department' => '66', 'status' => ExternalJobOfferStatus::EXCLUDED, 'latitude' => 42.6887, 'longitude' => 2.8948]);

        $autour = $this->sonde()->assertOk()->json('offres_partenaires.autour_de_perpignan');

        $this->assertSame(['10_km' => 1, '30_km' => 1, '50_km' => 1, '100_km' => 2], $autour);

        // Et la distance elle-meme, par la formule exacte de la sonde.
        $narbonne = DB::selectOne(
            'SELECT 6371 * 2 * ASIN(SQRT(POWER(SIN(RADIANS(latitude - ?) / 2), 2)'
            .' + COS(RADIANS(?)) * COS(RADIANS(latitude)) * POWER(SIN(RADIANS(longitude - ?) / 2), 2))) AS km'
            .' FROM external_job_offers WHERE department = ?',
            [DeployController::PERPIGNAN_LATITUDE, DeployController::PERPIGNAN_LATITUDE, DeployController::PERPIGNAN_LONGITUDE, '11'],
        );
        $this->assertEqualsWithDelta(55.78, (float) $narbonne->km, 0.05);
    }

    // SQLite n'a pas VERSION() ni ST_Distance_Sphere : chacun est signale
    // indisponible, et le reste de la sonde repond normalement.
    public function test_server_probes_degrade_gracefully_on_sqlite(): void
    {
        $serveur = $this->sonde()->assertOk()->json('serveur');

        $this->assertStringStartsWith('indisponible', $serveur['base_de_donnees']['version']);
        $this->assertFalse($serveur['base_de_donnees']['st_distance_sphere']['disponible']);
        $this->assertArrayHasKey('erreur', $serveur['base_de_donnees']['st_distance_sphere']);
        $this->assertSame(PHP_VERSION, $serveur['php']);
    }

    // ------------------------------------------------------------------
    // AUCUNE donnee personnelle : la sonde lit de vrais profils, elle ne
    // doit renvoyer que des comptages. Ni nom, ni email, ni ville, ni code
    // postal complet, ni adresse, ni telephone, ni URL de photo ou de CV.
    // ------------------------------------------------------------------

    public function test_geo_stats_never_leak_personal_data(): void
    {
        $this->candidat([
            'first_name' => 'Rostom', 'last_name' => 'Ghazli', 'phone' => '0612345678',
            'address' => '12 rue des Lilas', 'city' => 'Perpignan', 'postal_code' => '66000',
            'birth_date' => '2005-03-14', 'driving_license' => 'Permis B',
            'photo_url' => '/storage/photos/rostom.jpg', 'cv_file_url' => '/storage/cvs/rostom.pdf',
            'bio' => 'Passionne de cuisine.',
        ], ['email' => 'rostomghazli64@gmail.com']);

        $response = $this->sonde()->assertOk();

        foreach ([
            'Rostom', 'Ghazli', 'rostomghazli64', 'gmail', '0612345678', 'rue des Lilas',
            'Perpignan', '66000', '2005-03-14', 'Permis B', 'rostom.jpg', 'rostom.pdf', 'cuisine',
        ] as $interdit) {
            $response->assertDontSee($interdit, false);
        }
    }

    // ------------------------------------------------------------------
    // Le decoupage en departements, sur des valeurs choisies
    // ------------------------------------------------------------------

    public function test_department_is_derived_from_the_postal_code(): void
    {
        $this->assertSame('66', DeployController::departementDuCodePostal('66000'));
        $this->assertSame('06', DeployController::departementDuCodePostal('06000'));
        $this->assertSame('66', DeployController::departementDuCodePostal(' 66 000 '));
        $this->assertSame('66', DeployController::departementDuCodePostal('F-66000'));
        $this->assertSame('2A', DeployController::departementDuCodePostal('20000'));
        $this->assertSame('2A', DeployController::departementDuCodePostal('20190'));
        $this->assertSame('2B', DeployController::departementDuCodePostal('20200'));
        $this->assertSame('2B', DeployController::departementDuCodePostal('20600'));
        $this->assertSame('971', DeployController::departementDuCodePostal('97110'));
        $this->assertSame('974', DeployController::departementDuCodePostal('97400'));
        $this->assertSame('976', DeployController::departementDuCodePostal('97600'));
        $this->assertSame('988', DeployController::departementDuCodePostal('98800'));
        $this->assertSame('inconnu', DeployController::departementDuCodePostal(null));
        $this->assertSame('inconnu', DeployController::departementDuCodePostal(''));
        $this->assertSame('inconnu', DeployController::departementDuCodePostal('   '));
        $this->assertSame('inconnu', DeployController::departementDuCodePostal('Perpignan'));
        $this->assertSame('inconnu', DeployController::departementDuCodePostal('6600'));
        $this->assertSame('inconnu', DeployController::departementDuCodePostal('0612345678'));
    }
}
