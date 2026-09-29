<?php

namespace Tests\Feature;

use App\Enums\InterestDecision;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\AideMatch;
use Tests\TestCase;

/**
 * La route de deploiement /deploy/{token}/matches-remind
 * (DeployController::matchesRemind).
 *
 * POURQUOI CE FICHIER EXISTE. Le 2026-09-29, cette route repondait 500 en
 * production alors que MatchReminderServiceTest (13 tests) etait vert : ce
 * fichier appelle `$this->service()->run()` directement, jamais la route
 * HTTP. La vraie cause — un `use App\Services\MatchReminderService;`
 * manquant dans DeployController — ne pouvait donc jamais se voir dans la
 * suite. C'est exactement la lecon du 2026-09-04 sur le selftest : un
 * instrument qui ne traverse pas tout le chemin donne une fausse assurance.
 * Ce test traverse la vraie route.
 */
class DeployMatchesRemindTest extends TestCase
{
    use AideMatch;
    use RefreshDatabase;

    private const TOKEN = 'secret-token';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.deploy_token', self::TOKEN);
    }

    public function test_the_dry_run_route_actually_resolves_the_service(): void
    {
        // Le seul fait que ceci reponde 200 est le test : un `use` manquant
        // fait echouer la resolution du service AVANT que la moindre ligne
        // de logique ne s'execute, quel que soit l'etat de la base.
        $this->get('/deploy/'.self::TOKEN.'/matches-remind')
            ->assertOk()
            ->assertJsonPath('mode', 'a blanc (rien envoye, rien ecrit)');
    }

    public function test_the_dry_run_route_counts_a_real_pending_reminder(): void
    {
        [$candidat, , $offre] = $this->couple();
        $ligne = $this->interet($candidat, $offre, [
            'employer_decision' => InterestDecision::LIKE,
            'employer_decided_at' => now()->subDays(4),
        ]);

        $reponse = $this->get('/deploy/'.self::TOKEN.'/matches-remind')
            ->assertOk()
            ->json();

        $this->assertSame(1, $reponse['total']);
        // A blanc : rien n'a ete ecrit, une seconde passe recompterait pareil.
        $this->assertNull($ligne->fresh()->reminder_stage);
    }

    /** @return array{0: User, 1: User, 2: JobOffer} */
    private function couple(): array
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();

        return [$candidat, $employeur, $this->offrePubliee($employeur)];
    }

    private function interet($candidat, $offre, array $attributs)
    {
        return OfferInterest::create(array_merge([
            'candidate_profile_id' => $candidat->candidateProfile->id,
            'job_offer_id' => $offre->id,
        ], $attributs));
    }
}
