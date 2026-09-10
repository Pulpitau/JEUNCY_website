<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Les taches planifiees s'executent-elles vraiment ?
 *
 * DeploySchedulerCheckTest verifie qu'elles sont DECLAREES. Ce fichier verifie
 * qu'elles PARTENT, ce qui n'est pas la meme chose du tout.
 *
 * Le 2026-09-10, le battement a revele qu'aucune des six taches n'avait jamais
 * tourne : Laravel n'execute une tache que si son expression cron correspond a
 * la minute EXACTE ou schedule:run est lance, il ne rattrape rien, et le cron
 * OVH nous appelle a une minute arbitraire (09:41 ce jour-la). Toutes les
 * taches etaient en minute 0. Panne totale, silencieuse, pendant des semaines.
 *
 * Ces tests interrogent le vrai planificateur de l'application a des heures
 * simulees. Ils echouent si quelqu'un reintroduit un jour ->daily(),
 * ->hourly() ou ->weekly() ici.
 */
class ScheduleRunsAtAnyMinuteTest extends TestCase
{
    private const TACHES_QUOTIDIENNES = [
        'job-offers:expire',
        'job-offers:archive-expired-trials',
        'cvs:archive-inactive',
        'job-offers:notify-matching-candidates',
    ];

    /**
     * Ce que schedule:run executerait a cet instant precis.
     *
     * @return array<string, bool>
     */
    private function tachesLancees(string $moment): array
    {
        Carbon::setTestNow($moment);

        // withSchedule() s'accroche a Artisan::starting() : le planificateur
        // reste vide tant que la console n'a pas demarre (meme piege que dans
        // DeployController::scheduler). Un appel Artisan quelconque le peuple.
        Artisan::call('list', ['--raw' => true]);

        $lancees = [];
        foreach (app(Schedule::class)->events() as $event) {
            $nom = $event->command
                ? trim(preg_replace('/^.*artisan.?/', '', $event->command), " '\"")
                : 'battement';

            // Les DEUX conditions que verifie schedule:run : l'expression cron
            // correspond a la minute courante, et les filtres ->when() passent.
            $lancees[$nom] = $event->isDue($this->app) && $event->filtersPass($this->app);
        }

        return $lancees;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // LE test. Une minute arbitraire, choisie parce que c'est exactement celle
    // ou le cron OVH passait le jour ou la panne a ete decouverte.
    public function test_daily_tasks_run_even_when_the_cron_fires_at_a_random_minute(): void
    {
        $lancees = $this->tachesLancees('2026-09-11 09:41:00');

        foreach (self::TACHES_QUOTIDIENNES as $commande) {
            $this->assertTrue(
                $lancees[$commande] ?? false,
                "{$commande} ne partirait pas si le cron passait a 09:41 — c'est la panne du 2026-09-10.",
            );
        }
    }

    // Le pendant indispensable : toujours dues ne doit pas vouloir dire
    // executees a chaque passage du cron.
    public function test_a_daily_task_does_not_run_twice_in_the_same_day(): void
    {
        foreach (self::TACHES_QUOTIDIENNES as $commande) {
            Cache::forever("planificateur.derniere_passe.{$commande}", '2026-09-11');
        }

        $lancees = $this->tachesLancees('2026-09-11 10:41:00');

        foreach (self::TACHES_QUOTIDIENNES as $commande) {
            $this->assertFalse(
                $lancees[$commande] ?? true,
                "{$commande} repartirait une seconde fois le meme jour.",
            );
        }
    }

    public function test_a_daily_task_runs_again_the_next_day(): void
    {
        foreach (self::TACHES_QUOTIDIENNES as $commande) {
            Cache::forever("planificateur.derniere_passe.{$commande}", '2026-09-11');
        }

        $lancees = $this->tachesLancees('2026-09-12 03:17:00');

        foreach (self::TACHES_QUOTIDIENNES as $commande) {
            $this->assertTrue($lancees[$commande] ?? false, "{$commande} ne repartirait pas le lendemain.");
        }
    }

    // La purge des telechargements de CV est hebdomadaire : elle applique la
    // duree de conservation annoncee dans la politique de confidentialite, donc
    // sa cadence doit rester une vraie cadence, pas un passage par jour.
    public function test_the_weekly_purge_waits_for_the_next_week(): void
    {
        $cle = 'planificateur.derniere_passe.cv-downloads:purge';
        Cache::forever($cle, Carbon::parse('2026-09-11')->startOfWeek()->toDateString());

        $this->assertFalse(
            $this->tachesLancees('2026-09-12 03:17:00')['cv-downloads:purge'] ?? true,
            'La purge hebdomadaire repartirait des le lendemain.',
        );

        $this->assertTrue(
            $this->tachesLancees('2026-09-16 03:17:00')['cv-downloads:purge'] ?? false,
            'La purge hebdomadaire ne repartirait jamais la semaine suivante.',
        );
    }

    // Les rappels de visio n'ont volontairement pas de marqueur : la fenetre de
    // rappel est d'une heure, ils doivent partir a chaque passage du cron.
    public function test_video_room_reminders_run_at_every_cron_pass(): void
    {
        $this->assertTrue($this->tachesLancees('2026-09-11 09:41:00')['video-rooms:send-reminders'] ?? false);
        $this->assertTrue($this->tachesLancees('2026-09-11 10:41:00')['video-rooms:send-reminders'] ?? false);
    }

    // Le battement doit rester toujours du : c'est lui qui prouve que le cron
    // tourne, et il ne prouverait plus rien s'il dependait d'une minute.
    public function test_the_heartbeat_is_always_due(): void
    {
        foreach (['2026-09-11 00:00:00', '2026-09-11 09:41:00', '2026-09-11 23:59:00'] as $moment) {
            $this->assertTrue($this->tachesLancees($moment)['battement'] ?? false, "Battement muet a {$moment}.");
        }
    }
}
