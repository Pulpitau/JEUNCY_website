<?php

namespace Tests\Feature;

use App\Enums\JobOfferStatus;
use App\Enums\UserRole;
use App\Models\CfaOrganization;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Outils ajoutes le 2026-09-23 (deploy-tools-30) : lire le journal d'erreurs
 * de production, et donner un code postal a une offre qui n'en a pas.
 *
 * La sonde de logs doit etre testee comme le reste — un outil de diagnostic
 * qui ment coute plus cher que pas d'outil du tout (lecon du selftest,
 * CLAUDE.md, lot 1).
 */
class DeployLogsAndOfferPostalCodeTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'jeton-de-test';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.deploy_token', $this->token);
    }

    private function ecrireJournal(string $contenu): string
    {
        $chemin = storage_path('logs/laravel.log');
        @mkdir(dirname($chemin), 0755, true);
        file_put_contents($chemin, $contenu);

        return $chemin;
    }

    public function test_the_logs_probe_refuses_a_wrong_token(): void
    {
        $this->get('/deploy/mauvais-jeton/logs')->assertNotFound();
    }

    public function test_it_returns_only_error_entries_most_recent_first(): void
    {
        $this->ecrireJournal(
            "[2026-09-23 08:00:00] production.INFO: Import LBA termine\n".
            "[2026-09-23 09:00:00] production.ERROR: Premiere erreur {\"exception\":\"...\"}\n".
            "#0 /app/Http/Controllers/Truc.php(12)\n".
            "#1 /app/Services/Machin.php(34)\n".
            "[2026-09-23 10:00:00] production.WARNING: Cache vide\n".
            "[2026-09-23 11:00:00] production.ERROR: Seconde erreur\n",
        );

        $reponse = $this->get("/deploy/{$this->token}/logs")->assertOk();

        $erreurs = $reponse->json('erreurs');
        $this->assertCount(2, $erreurs);
        $this->assertStringContainsString('Seconde erreur', $erreurs[0], 'La plus recente doit venir en premier.');
        $this->assertStringContainsString('Premiere erreur', $erreurs[1]);
        $this->assertStringNotContainsString('Import LBA termine', implode("\n", $erreurs));
        $this->assertStringNotContainsString('Cache vide', implode("\n", $erreurs));
    }

    public function test_it_hides_emails_and_tokens(): void
    {
        $this->ecrireJournal(
            "[2026-09-23 09:00:00] production.ERROR: Echec pour lea.girard@example.com avec token=abcdef123456\n",
        );

        $erreurs = $this->get("/deploy/{$this->token}/logs")->assertOk()->json('erreurs');

        $this->assertStringNotContainsString('lea.girard@example.com', $erreurs[0]);
        $this->assertStringNotContainsString('abcdef123456', $erreurs[0]);
        $this->assertStringContainsString('***', $erreurs[0]);
    }

    public function test_it_can_filter_on_a_word(): void
    {
        $this->ecrireJournal(
            "[2026-09-23 09:00:00] production.ERROR: probleme sur skills\n".
            "[2026-09-23 10:00:00] production.ERROR: probleme sur experiences\n",
        );

        $erreurs = $this->get("/deploy/{$this->token}/logs?contient=skills")->assertOk()->json('erreurs');

        $this->assertCount(1, $erreurs);
        $this->assertStringContainsString('skills', $erreurs[0]);
    }

    public function test_an_empty_journal_says_so_instead_of_failing(): void
    {
        foreach (glob(storage_path('logs/*.log')) ?: [] as $fichier) {
            @unlink($fichier);
        }

        $this->get("/deploy/{$this->token}/logs")->assertOk()->assertJsonStructure(['journal']);
    }

    // ------------------------------------------------------------------

    private function offreSansCodePostal(): JobOffer
    {
        $user = User::create(['email' => 'cfa@example.com', 'password_hash' => 'x', 'role' => UserRole::CFA]);
        $cfa = CfaOrganization::create(['user_id' => $user->id, 'name' => 'IDA', 'city' => 'Perpignan']);

        return JobOffer::create([
            'cfa_organization_id' => $cfa->id,
            'title' => 'Negociateur technico commercial',
            'description' => 'Offre de test.',
            'contract_type' => 'ALTERNANCE',
            'city' => 'Perpignan',
            'status' => JobOfferStatus::PUBLISHED,
        ]);
    }

    public function test_without_a_postal_code_it_only_shows_the_offer(): void
    {
        $offre = $this->offreSansCodePostal();

        $this->get("/deploy/{$this->token}/offre/{$offre->id}/code-postal")
            ->assertOk()
            ->assertJsonPath('offre.ville', 'Perpignan')
            ->assertJsonPath('offre.code_postal', null);

        $this->assertNull($offre->fresh()->postal_code, 'Rien ne doit etre ecrit sans ?cp=.');
    }

    public function test_it_writes_the_postal_code(): void
    {
        $offre = $this->offreSansCodePostal();

        $this->get("/deploy/{$this->token}/offre/{$offre->id}/code-postal?cp=66000")
            ->assertOk()
            ->assertJsonPath('apres.code_postal', '66000');

        $this->assertSame('66000', $offre->fresh()->postal_code);
    }

    public function test_it_refuses_something_that_is_not_a_postal_code(): void
    {
        $offre = $this->offreSansCodePostal();

        $this->get("/deploy/{$this->token}/offre/{$offre->id}/code-postal?cp=66")->assertStatus(400);
        $this->assertNull($offre->fresh()->postal_code);
    }

    public function test_an_unknown_offer_answers_clearly(): void
    {
        $this->get("/deploy/{$this->token}/offre/999999/code-postal?cp=66000")->assertStatus(404);
    }
}
