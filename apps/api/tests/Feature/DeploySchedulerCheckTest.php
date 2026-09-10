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

        // Le cablage, pas seulement les fonctions : ces deux blocs doivent
        // REELLEMENT figurer dans la reponse. Une fonction juste mais jamais
        // appelee est exactement ce qui a coute quatre allers-retours en
        // septembre.
        $response->assertJsonStructure([
            'cron' => ['etat'],
            'dernieres_passes' => ['job-offers:expire' => ['etat']],
        ]);
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

    // LE BATTEMENT. Tout ce qui precede verifie ce que Laravel a l'INTENTION de
    // faire. Un cron OVH arrete laisserait ces controles strictement
    // identiques — c'est cet angle mort que les trois tests suivants ferment.

    public function test_heartbeat_reports_the_cron_as_alive_when_it_beat_recently(): void
    {
        $verdict = DeployController::verdictBattement(
            '2026-09-10 09:00:00',
            new \DateTimeImmutable('2026-09-10 09:12:00'),
        );

        $this->assertSame(12, $verdict['il_y_a_minutes']);
        $this->assertStringContainsString('ok', $verdict['etat']);
    }

    // Le cron OVH passe toutes les heures : au-dela de 70 minutes, un passage a
    // ete manque. C'est le seul signal qui distingue "planifie" de "reellement
    // execute", et il doit crier, pas chuchoter.
    public function test_heartbeat_denounces_a_silent_cron(): void
    {
        $verdict = DeployController::verdictBattement(
            '2026-09-10 05:00:00',
            new \DateTimeImmutable('2026-09-10 09:12:00'),
        );

        $this->assertSame(252, $verdict['il_y_a_minutes']);
        $this->assertStringContainsString('CRON MUET', $verdict['etat']);
    }

    // Juste apres le deploiement du battement, la cle n'existe pas encore. Ne
    // pas confondre ce cas avec une panne : le message doit enoncer les deux
    // lectures possibles plutot que d'accuser a tort.
    public function test_heartbeat_absent_does_not_accuse_the_cron(): void
    {
        $verdict = DeployController::verdictBattement(null, new \DateTimeImmutable);

        $this->assertNull($verdict['il_y_a_minutes']);
        $this->assertStringContainsString('AUCUN BATTEMENT', $verdict['etat']);
    }

    // LES PASSES PAR TACHE. Le battement prouve que le cron passe ; il ne dit
    // rien de ce qui s'execute pendant ce passage. C'est exactement cet ecart
    // qui a laisse six taches muettes pendant des semaines : le cron tournait,
    // et aucune tache ne partait.

    public function test_a_task_that_ran_today_is_reported_ok(): void
    {
        $verdict = DeployController::verdictPasses(
            ['job-offers:expire' => '2026-09-11'],
            new \DateTimeImmutable('2026-09-11 13:00:00'),
        );

        $this->assertSame(0, $verdict['job-offers:expire']['il_y_a_jours']);
        $this->assertSame('ok', $verdict['job-offers:expire']['etat']);
    }

    // Une quotidienne part au premier passage du cron apres minuit : la voir
    // dater d'hier est normal en pleine nuit, ce n'est pas un retard.
    public function test_a_daily_task_that_ran_yesterday_is_not_flagged(): void
    {
        $verdict = DeployController::verdictPasses(
            ['job-offers:expire' => '2026-09-10'],
            new \DateTimeImmutable('2026-09-11 00:20:00'),
        );

        $this->assertSame('ok', $verdict['job-offers:expire']['etat']);
    }

    // Au-dela, un passage a ete manque : c'est la panne qu'on veut voir crier.
    public function test_a_daily_task_stuck_for_days_is_denounced(): void
    {
        $verdict = DeployController::verdictPasses(
            ['cvs:archive-inactive' => '2026-09-05'],
            new \DateTimeImmutable('2026-09-11 13:00:00'),
        );

        $this->assertSame(6, $verdict['cvs:archive-inactive']['il_y_a_jours']);
        $this->assertStringContainsString('EN RETARD', $verdict['cvs:archive-inactive']['etat']);
    }

    // L'hebdomadaire a droit a six jours : son marqueur porte le lundi de la
    // semaine en cours. La signaler en retard chaque vendredi aurait rendu le
    // controle inutile a force de fausses alertes.
    public function test_a_weekly_task_is_allowed_to_be_days_old(): void
    {
        $maintenant = new \DateTimeImmutable('2026-09-11 13:00:00');

        $this->assertSame(
            'ok',
            DeployController::verdictPasses(['cv-downloads:purge' => '2026-09-07'], $maintenant)['cv-downloads:purge']['etat'],
        );
        $this->assertStringContainsString(
            'EN RETARD',
            DeployController::verdictPasses(['cv-downloads:purge' => '2026-08-31'], $maintenant)['cv-downloads:purge']['etat'],
        );
    }

    // Juste apres le deploiement du correctif, aucun marqueur n'existe encore.
    // Le message doit enoncer les deux lectures possibles plutot que d'accuser.
    public function test_a_task_without_any_marker_is_not_accused(): void
    {
        $verdict = DeployController::verdictPasses([], new \DateTimeImmutable);

        foreach (array_keys(DeployController::TACHES_A_MARQUEUR) as $commande) {
            $this->assertNull($verdict[$commande]['derniere_passe']);
            $this->assertStringContainsString('JAMAIS PASSEE', $verdict[$commande]['etat']);
        }
    }

    // Les rappels de visio n'ont pas de marqueur : ils partent a chaque passage
    // du cron. Les attendre ici produirait une fausse alerte permanente.
    public function test_video_room_reminders_are_not_expected_to_have_a_marker(): void
    {
        $this->assertArrayNotHasKey('video-rooms:send-reminders', DeployController::TACHES_A_MARQUEUR);
    }
}
