<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\GeocodeCache;
use App\Models\JobOffer;
use App\Models\Notification;
use App\Models\OfferInterest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Le selftest de deploiement (/deploy/{token}/selftest), volet « modele
 * match » du lot 1.
 *
 * POURQUOI TESTER UN OUTIL DE DIAGNOSTIC. Le piege du 2026-09-04 est
 * exactement celui-la : le selftest appelait le SERVICE sans passer par le
 * controleur, donc il validait precisement la partie qui marchait et donnait
 * une fausse assurance pendant cinq allers-retours. Un instrument de mesure
 * non verifie ne mesure rien.
 *
 * Deux choses sont gardees ici, et ce sont les deux qui rendent l'outil
 * utilisable : le parcours traverse REELLEMENT le noyau HTTP jusqu'a
 * l'insertion, et il ne laisse RIEN derriere lui.
 */
class DeploySelfTestTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'secret-token';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.deploy_token', self::TOKEN);
        // Le perimetre employeur doit etre ouvert sur le 66, sinon
        // discover/candidates repond MATCH_NOT_OPEN_HERE : c'est le defaut
        // de config/services.php, pose ici explicitement pour que le test ne
        // depende pas d'un .env local.
        Config::set('services.jeuncy.match_departements', '66');
    }

    private function sonde(): TestResponse
    {
        return $this->get('/deploy/'.self::TOKEN.'/selftest');
    }

    public function test_the_probe_stays_closed_without_the_token(): void
    {
        $this->get('/deploy/mauvais-token/selftest')->assertNotFound();
    }

    public function test_every_check_reports_ok(): void
    {
        $tests = $this->sonde()->assertOk()->json('tests');

        foreach ($tests as $nom => $resultat) {
            $this->assertTrue(
                $resultat['ok'] ?? false,
                "L'essai « {$nom} » a echoue : ".json_encode($resultat, JSON_UNESCAPED_UNICODE),
            );
        }
    }

    /**
     * Le cablage est le controle le moins cher et le plus rentable : une
     * version perimee d'un service se construit sans erreur et n'appelle
     * simplement jamais son collaborateur. Panne totalement silencieuse.
     */
    public function test_wiring_check_names_every_service(): void
    {
        $cablage = $this->sonde()->assertOk()->json('tests.cablage_match.resultat');

        $this->assertSame([
            'JobOfferMatchService' => 'ok',
            'DiscoverService' => 'ok',
            'CvthequeService' => 'ok',
            'MatchService' => 'ok',
            'InterestService' => 'ok',
            'ApplicationService' => 'ok',
            'AccountService' => 'ok',
        ], $cablage);
    }

    /**
     * Le coeur de l'outil : deux decks, deux « Ca m'interesse », un match —
     * par de vraies requetes HTTP avec de vrais jetons, donc a travers les
     * middlewares de role et des 16 ans, les Form Requests, les controleurs
     * et les services.
     */
    public function test_the_match_journey_goes_all_the_way_to_a_match(): void
    {
        $parcours = $this->sonde()->assertOk()->json('tests.parcours_match.resultat');

        $this->assertSame(200, $parcours['discover_offers']['statut']);
        $this->assertTrue($parcours['discover_offers']['offre_de_sonde_presente']);
        // ~5 km : un zero passerait aussi bien avec une haversine cassee.
        $this->assertEqualsWithDelta(5.0, $parcours['discover_offers']['distance_km_de_la_sonde'], 1.0);

        $this->assertSame(200, $parcours['discover_candidates']['statut']);
        $this->assertTrue($parcours['discover_candidates']['profil_de_sonde_present']);
        $this->assertSame('aucune', $parcours['discover_candidates']['cles_interdites_trouvees']);

        $this->assertSame(201, $parcours['interet_employeur']['statut']);
        $this->assertFalse($parcours['interet_employeur']['matched'], 'un seul oui ne fait pas un match');

        $this->assertSame(201, $parcours['interet_candidat']['statut']);
        $this->assertTrue($parcours['interet_candidat']['matched'], 'les deux oui font le match');

        $this->assertSame('1 ligne(s) matchee(s)', $parcours['match_en_base']);
        $this->assertSame(1, $parcours['matches_candidat']['lignes']);
        $this->assertSame(1, $parcours['matches_employeur']['lignes']);
    }

    /**
     * La promesse « aucune donnee reelle touchee » : tout ce que la sonde
     * ecrit — comptes, entreprise, offre, profil, interets, notifications —
     * disparait avec la transaction.
     */
    public function test_the_probe_leaves_nothing_behind(): void
    {
        $avant = [
            'users' => User::count(),
            'companies' => Company::count(),
            'job_offers' => JobOffer::count(),
            'offer_interests' => OfferInterest::count(),
            'notifications' => Notification::count(),
            'geocode_cache' => GeocodeCache::count(),
        ];

        $this->sonde()->assertOk();

        $this->assertSame($avant, [
            'users' => User::count(),
            'companies' => Company::count(),
            'job_offers' => JobOffer::count(),
            'offer_interests' => OfferInterest::count(),
            'notifications' => Notification::count(),
            'geocode_cache' => GeocodeCache::count(),
        ]);
    }

    /**
     * La valeur d'enum des trois nouveaux types de notification. Sous SQLite
     * ce test ne prouve que l'ecriture ; c'est en production, sur MySQL, que
     * l'essai a du sens — d'ou son existence dans le selftest. Ce qu'on garde
     * ici, c'est que l'essai s'execute vraiment au lieu d'etre une chaine.
     */
    public function test_notification_enum_check_actually_inserts(): void
    {
        $resultat = $this->sonde()->assertOk()->json('tests.enum_notification_mysql.resultat');

        foreach (['NEW_MATCH', 'INTEREST_RECEIVED', 'MATCH_CLOSED'] as $type) {
            $this->assertStringContainsString($type, $resultat);
        }
    }

    public function test_perimeter_and_verification_state_are_reported(): void
    {
        $reponse = $this->sonde()->assertOk();

        $this->assertSame(['66'], $reponse->json('tests.perimetre.resultat'));

        $verifications = $reponse->json('tests.cfa_verifiee.resultat');
        foreach (['cfa_verifies', 'cfa_en_attente', 'entreprises_verifiees', 'offres_publiees_sans_coordonnees', 'communes_en_cache'] as $cle) {
            $this->assertArrayHasKey($cle, $verifications);
        }
    }

    // Le cache de communes doit servir : une commune deja resolue ne repart
    // jamais sur le reseau, ce qui est ce qui rend un geocodage synchrone
    // tenable sur un hebergement sans worker.
    public function test_geocoding_reads_the_cache_without_network(): void
    {
        $this->assertStringContainsString(
            'cache lu sans reseau',
            $this->sonde()->assertOk()->json('tests.geocodage_simule.resultat'),
        );
    }

    public function test_the_version_endpoint_lists_every_lot_1_file(): void
    {
        $reponse = $this->get('/deploy/'.self::TOKEN.'/version')->assertOk();

        $this->assertSame('deploy-tools-30', $reponse->json('version_outils_deploiement'));

        $fichiers = $reponse->json('fichiers');

        // Les six fichiers dont l'absence a ete la panne, deux fois : un
        // enum sans sa valeur, un service sans son cablage, une route
        // jamais incluse.
        foreach ([
            'routes/api.php',
            'routes/api/discover.php',
            'app/Enums/NotificationType.php',
            'app/Support/Haversine.php',
            'app/Presenters/CandidateCardPresenter.php',
            'app/Http/Middleware/EnsureMatchAge.php',
            'database/migrations/2026_09_22_100010_add_match_types_to_notifications_type_enum.php',
        ] as $chemin) {
            $this->assertArrayHasKey($chemin, $fichiers, "{$chemin} doit etre surveille par /version.");
            $this->assertNotSame('ABSENT', $fichiers[$chemin], "{$chemin} est introuvable dans le depot.");
        }
    }

    /**
     * Le filet qui empeche la liste de /version de se perimer : tout fichier
     * PHP du lot 1 doit y figurer. C'est la lecon des 2026-09-04 et
     * 2026-09-08 rendue automatique — un fichier absent du serveur produit
     * une panne indistinguable d'un bug de code, et on ne pense jamais a
     * ajouter a la liste le fichier qu'on vient seulement de MODIFIER.
     */
    public function test_every_match_file_is_watched(): void
    {
        $surveilles = array_keys($this->get('/deploy/'.self::TOKEN.'/version')->assertOk()->json('fichiers'));

        $attendus = array_merge(
            ['app/Support/Haversine.php', 'app/Support/PostalCodes.php', 'app/Support/MatchPerimeter.php',
                'app/Presenters/CandidateCardPresenter.php', 'app/Rules/ValidSiret.php',
                'app/Http/Middleware/EnsureMatchAge.php', 'routes/api.php', 'bootstrap/app.php', 'config/services.php'],
            array_map(
                fn (string $f) => 'database/migrations/'.basename($f),
                glob(base_path('database/migrations/2026_09_22_1000*.php')) ?: [],
            ),
            array_map(
                fn (string $f) => 'app/Services/'.basename($f),
                array_filter(
                    glob(base_path('app/Services/*.php')) ?: [],
                    fn (string $f) => in_array(basename($f), [
                        'GeocodingService.php', 'CompanyVerificationService.php', 'MatchClosingService.php',
                        'BlockService.php', 'MatchScorer.php', 'DiscoverService.php', 'InterestService.php',
                        'MatchService.php', 'ExternalInterestService.php', 'ReportService.php',
                    ], true),
                ),
            ),
        );

        $manquants = array_values(array_diff($attendus, $surveilles));

        $this->assertSame([], $manquants, 'Fichiers du lot 1 absents de la liste de /version : '.implode(', ', $manquants));
    }
}
