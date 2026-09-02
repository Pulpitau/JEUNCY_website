<?php

namespace Tests\Feature;

use App\Http\Controllers\DeployController;
use App\Services\CvService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DeploySchedulerCheckTest extends TestCase
{
    public function test_scheduler_check_requires_valid_deploy_token(): void
    {
        Config::set('app.deploy_token', 'secret-token');

        $this->get('/deploy/wrong-token/scheduler')->assertNotFound();
    }

    public function test_scheduler_check_is_inert_when_no_deploy_token_configured(): void
    {
        Config::set('app.deploy_token', null);

        $this->get('/deploy/anything/scheduler')->assertNotFound();
    }

    // Le vidage de cache est l'outil dont on se sert pour REPARER un
    // deploiement : il ne doit jamais tomber, quelle que soit la configuration
    // de l'hebergeur. Une premiere version appelait opcache_reset() sans filet
    // et repondait 500 quand l'API OPcache etait restreinte — l'outil de
    // reparation cassait au pire moment (2026-09-02).
    public function test_clear_cache_never_fails(): void
    {
        Config::set('app.deploy_token', 'secret-token');

        $response = $this->get('/deploy/secret-token/clear-cache');

        $response->assertOk();
        $response->assertSee('OPcache', false);
    }

    // Le rapport de version permet de comparer le serveur au depot sans acces
    // SSH : sans lui, impossible de distinguer un correctif inefficace d'un
    // correctif jamais deploye.
    public function test_version_reports_the_deployed_files(): void
    {
        Config::set('app.deploy_token', 'secret-token');

        $response = $this->get('/deploy/secret-token/version');

        $response->assertOk();
        $response->assertJsonPath('version_moteur_cv', CvService::LAYOUT_VERSION);
        $response->assertJsonStructure([
            'fichiers' => ['app/Services/CvService.php' => ['empreinte', 'modifie_le', 'octets']],
        ]);
    }

    public function test_version_requires_a_valid_deploy_token(): void
    {
        Config::set('app.deploy_token', 'secret-token');

        $this->get('/deploy/wrong-token/version')->assertNotFound();
    }

    // Le cas nominal : chaque tache attendue est enregistree ET planifiee.
    public function test_scheduler_check_reports_ok_for_every_expected_task(): void
    {
        Config::set('app.deploy_token', 'secret-token');

        $response = $this->get('/deploy/secret-token/scheduler');

        $response->assertOk();
        foreach ([
            'job-offers:expire',
            'job-offers:archive-expired-trials',
            'cvs:archive-inactive',
            'video-rooms:send-reminders',
            'cv-downloads:purge',
        ] as $commande) {
            $response->assertJsonPath("taches.{$commande}", 'ok');
        }
    }

    // Le cas que ce controle existe pour attraper : bootstrap/app.php pas
    // redeploye, donc la commande existe mais n'est planifiee nulle part. Rien
    // ne plante, rien ne s'execute non plus — c'est precisement ce silence que
    // le controle doit rompre.
    public function test_registered_but_not_scheduled_is_flagged(): void
    {
        $rapport = DeployController::comparerTaches(
            enregistrees: DeployController::TACHES_ATTENDUES,
            planifiees: [], // planificateur vide
        );

        $this->assertSame(
            'presente mais NON PLANIFIEE — ne tournera jamais (bootstrap/app.php a redeployer)',
            $rapport['video-rooms:send-reminders'],
        );
    }

    // L'inverse, et c'est le cas le plus dangereux : une commande planifiee
    // dont le fichier manque fait echouer schedule:run EN ENTIER, donc les
    // trois autres taches cessent aussi de tourner. C'est ce qui a suivi la
    // panne du 2026-08-07, quand le fichier de commande a ete retire du serveur
    // sans toucher a la planification.
    public function test_scheduled_but_missing_command_file_is_flagged(): void
    {
        $rapport = DeployController::comparerTaches(
            enregistrees: ['job-offers:expire'], // fichier de la commande visio absent
            planifiees: DeployController::TACHES_ATTENDUES,
        );

        $this->assertSame(
            'PLANIFIEE MAIS FICHIER DE COMMANDE ABSENT — casse tout schedule:run',
            $rapport['video-rooms:send-reminders'],
        );
        $this->assertSame('ok', $rapport['job-offers:expire']);
    }

    public function test_command_absent_everywhere_is_flagged(): void
    {
        $rapport = DeployController::comparerTaches(enregistrees: [], planifiees: []);

        $this->assertSame('ABSENTE des deux cotes', $rapport['video-rooms:send-reminders']);
    }
}
