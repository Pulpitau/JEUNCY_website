<?php

namespace Tests;

use Closure;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Reponse du registre des entreprises pour le test en cours. Null =
     * registre vide (aucun SIRET connu). Un test qui veut un NAF precis
     * assigne ici une closure recevant la requete et renvoyant une reponse
     * Http::response(...) — un second Http::fake sur la meme URL ne
     * suffirait pas, le premier stub enregistre l'emporte toujours.
     */
    protected ?Closure $registreEntreprises = null;

    /**
     * Reponse du geocodeur (Geoplateforme IGN, GeocodingService) pour le
     * test en cours. Null = aucune commune trouvee (features vide), donc
     * aucune coordonnee ecrite. Meme mecanique que $registreEntreprises.
     */
    protected ?Closure $geocodeur = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activerTrigonometrieSqlite();

        // Le registre des entreprises (TrainingOrganizationDetector::nafFor)
        // ne doit jamais etre appele pour de vrai depuis un test : lent,
        // dependant du reseau, et une fiche creee avec un SIRET quelconque
        // ne doit pas se retrouver bloquee parce que ce numero existe.
        //
        // Meme regle pour le geocodeur : des que la creation d'un profil,
        // d'une offre ou d'une organisation declenche un geocodage, des
        // centaines de tests existants qui posent city / postal_code
        // partiraient interroger l'IGN. Le stub repond « aucune commune »
        // par defaut ; un test qui veut des coordonnees assigne $geocodeur.
        $this->registreEntreprises = null;
        $this->geocodeur = null;
        Http::fake([
            'recherche-entreprises.api.gouv.fr/*' => function ($request) {
                return $this->registreEntreprises
                    ? ($this->registreEntreprises)($request)
                    : Http::response(['results' => [], 'total_results' => 0]);
            },
            'data.geopf.fr/*' => function ($request) {
                return $this->geocodeur
                    ? ($this->geocodeur)($request)
                    : Http::response(['type' => 'FeatureCollection', 'features' => []]);
            },
            // Ceinture et bretelles sur Resend : phpunit.xml vide deja
            // RESEND_API_KEY pour que MailService journalise au lieu
            // d'envoyer, mais il a suffi qu'un test pose $_ENV sans le
            // restaurer (DeployEnvCheckTest) pour que toute la suite se mette
            // a poster de vrais emails. Le stub rend l'accident impossible.
            'api.resend.com/*' => Http::response(['id' => 'test-email-id']),
        ]);

        // Http::fake avec un tableau laisse passer vers le reseau toute URL
        // non listee : un appel oublie doit echouer bruyamment plutot que
        // partir en silence.
        Http::preventStrayRequests();
    }

    /**
     * SQLite n'a pas de fonctions trigonometriques : on lui prete celles de
     * PHP, avec les noms que la haversine ecrite en SQL appelle sur MySQL
     * (SIN, COS, ASIN, SQRT, RADIANS, POWER). Tout test herite ainsi du
     * calcul de distance sans rien faire. Sans effet sur un autre pilote.
     *
     * Appele apres parent::setUp() : c'est la que RefreshDatabase a ouvert
     * la connexion en memoire du test en cours (une par test).
     */
    private function activerTrigonometrieSqlite(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        $pdo = DB::connection()->getPdo();

        if (! method_exists($pdo, 'sqliteCreateFunction')) {
            return;
        }

        foreach (['sin' => 'sin', 'cos' => 'cos', 'asin' => 'asin', 'sqrt' => 'sqrt', 'radians' => 'deg2rad'] as $nom => $php) {
            $pdo->sqliteCreateFunction($nom, $php, 1);
        }
        $pdo->sqliteCreateFunction('power', 'pow', 2);
        $pdo->sqliteCreateFunction('pow', 'pow', 2);
    }
}
