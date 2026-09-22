<?php

namespace Tests\Feature;

use App\Enums\ExternalInterestDecision;
use App\Models\ExternalInterest;
use App\Models\ExternalJobOffer;
use App\Services\ExternalInterestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AideMatch;
use Tests\TestCase;

/**
 * Offres partenaires (La bonne alternance) : « Je garde » / « Passer ».
 *
 * Le test qui compte est test_kept_survive_offer_deletion : l'import LBA
 * supprime chaque nuit les offres absentes de l'export. Sans la copie des
 * champs dans la ligne, la liste « Gardees » se viderait toute seule, et le
 * candidat perdrait exactement ce qu'il avait demande a garder.
 */
class ExternalInterestServiceTest extends TestCase
{
    use AideMatch;
    use RefreshDatabase;

    private ExternalInterestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chargerLesRoutesDuMatch();
        $this->service = $this->app->make(ExternalInterestService::class);
    }

    public function test_keep_denormalizes_offer(): void
    {
        $candidat = $this->candidat();
        $offre = ExternalJobOffer::factory()->active()->located()->create([
            'company_name' => 'Au pain doré',
            'company_siret' => '12345678901234',
            'title' => 'Boulanger H/F',
            'city' => 'Canet-en-Roussillon',
            'apply_url' => 'https://exemple.test/offre/1',
        ]);

        $ligne = $this->service->decide($candidat, $offre, ExternalInterestDecision::KEEP);

        $this->assertSame('Au pain doré', $ligne->company_name);
        $this->assertSame('12345678901234', $ligne->company_siret);
        $this->assertSame('Boulanger H/F', $ligne->title);
        $this->assertSame('Canet-en-Roussillon', $ligne->city);
        $this->assertSame('https://exemple.test/offre/1', $ligne->apply_url);
        $this->assertNotNull($ligne->decided_at);
    }

    public function test_kept_survive_offer_deletion(): void
    {
        $candidat = $this->candidat();
        $offre = ExternalJobOffer::factory()->active()->located()->create(['title' => 'Boulanger H/F']);

        $this->service->decide($candidat, $offre, ExternalInterestDecision::KEEP);

        // Passe de nuit de l'import : l'offre disparait de l'export.
        $offre->delete();

        $gardees = $this->service->listKept($candidat);

        $this->assertCount(1, $gardees);
        $this->assertNull($gardees->first()->external_job_offer_id);
        $this->assertSame('Boulanger H/F', $gardees->first()->title);
    }

    public function test_a_second_decision_replaces_the_first(): void
    {
        $candidat = $this->candidat();
        $offre = ExternalJobOffer::factory()->active()->located()->create();

        $this->service->decide($candidat, $offre, ExternalInterestDecision::PASS);
        $this->service->decide($candidat, $offre, ExternalInterestDecision::KEEP);

        $this->assertSame(1, ExternalInterest::count());
        $this->assertSame(ExternalInterestDecision::KEEP, ExternalInterest::firstOrFail()->decision);
    }

    public function test_done_requires_ownership(): void
    {
        $candidat = $this->candidat([], ['email' => 'moi@example.test']);
        $intrus = $this->candidat([], ['email' => 'intrus@example.test']);
        $offre = ExternalJobOffer::factory()->active()->located()->create();

        $ligne = $this->service->decide($candidat, $offre, ExternalInterestDecision::KEEP);

        $this->withToken($this->jeton($intrus))
            ->patchJson('/api/external-interests/'.$ligne->id.'/done')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->withToken($this->jeton($candidat))
            ->patchJson('/api/external-interests/'.$ligne->id.'/done')
            ->assertOk();

        $this->assertNotNull($ligne->fresh()->done_at);
    }

    public function test_kept_list_puts_what_is_left_to_do_first(): void
    {
        $candidat = $this->candidat();
        $faite = ExternalJobOffer::factory()->active()->located()->create(['title' => 'Deja faite']);
        $aFaire = ExternalJobOffer::factory()->active()->located()->create(['title' => 'A faire']);

        $ligneFaite = $this->service->decide($candidat, $faite, ExternalInterestDecision::KEEP);
        $this->service->markDone($candidat, $ligneFaite);
        $this->service->decide($candidat, $aFaire, ExternalInterestDecision::KEEP);

        $titres = $this->service->listKept($candidat)->pluck('title')->all();

        $this->assertSame(['A faire', 'Deja faite'], $titres);
    }

    public function test_pass_hides_60_days(): void
    {
        // Le masquage lui-meme est verifie par DiscoverOffersTest (c'est la
        // pile qui l'applique). Ici on prouve que le geste pose bien la date
        // sur laquelle ce masquage s'appuie.
        $candidat = $this->candidat();
        $offre = ExternalJobOffer::factory()->active()->located()->create();

        $ligne = $this->service->decide($candidat, $offre, ExternalInterestDecision::PASS);

        $this->assertSame(ExternalInterestDecision::PASS, $ligne->decision);
        $this->assertTrue($ligne->decided_at->isToday());
    }

    public function test_undo_last_within_5_minutes(): void
    {
        $candidat = $this->candidat();
        $offre = ExternalJobOffer::factory()->active()->located()->create();

        $this->service->decide($candidat, $offre, ExternalInterestDecision::PASS);

        $this->withToken($this->jeton($candidat))
            ->deleteJson('/api/external-interests/last')
            ->assertOk()
            ->assertJsonPath('data.undone.external_job_offer_id', $offre->id);

        $this->assertSame(0, ExternalInterest::count());
    }

    public function test_undo_after_5_minutes_409(): void
    {
        $candidat = $this->candidat();
        $offre = ExternalJobOffer::factory()->active()->located()->create();

        $ligne = $this->service->decide($candidat, $offre, ExternalInterestDecision::PASS);
        $ligne->forceFill(['decided_at' => now()->subMinutes(6)])->save();

        $this->withToken($this->jeton($candidat))
            ->deleteJson('/api/external-interests/last')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'UNDO_WINDOW_EXPIRED');
    }

    public function test_the_kept_endpoint_returns_only_keeps(): void
    {
        $candidat = $this->candidat();
        $gardee = ExternalJobOffer::factory()->active()->located()->create(['title' => 'Gardee']);
        $passee = ExternalJobOffer::factory()->active()->located()->create(['title' => 'Passee']);

        $this->service->decide($candidat, $gardee, ExternalInterestDecision::KEEP);
        $this->service->decide($candidat, $passee, ExternalInterestDecision::PASS);

        $this->withToken($this->jeton($candidat))
            ->getJson('/api/external-interests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Gardee');
    }
}
