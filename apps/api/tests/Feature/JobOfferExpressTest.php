<?php

namespace Tests\Feature;

use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Enums\MatchClosedReason;
use App\Enums\OfferSector;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use App\Models\User;
use App\Services\JobOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Offre express et geolocalisation de l'offre (MOBILE.md §4.1 et §6).
 *
 * phpunit.xml eteint la gratuite pour garder le modele payant sous test :
 * ce fichier decrit la production, il la rallume (comme ModeGratuitTest).
 */
class JobOfferExpressTest extends TestCase
{
    use RefreshDatabase;

    private const PERPIGNAN = [2.894833, 42.688700];

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.jeuncy.gratuit', true);

        $this->geocodeur = fn () => Http::response([
            'features' => [[
                'geometry' => ['coordinates' => self::PERPIGNAN],
            ]],
        ]);
    }

    private function companyUser(array $attributes = []): User
    {
        $company = Company::factory()->create($attributes);

        return $company->user->fresh();
    }

    public function test_express_creates_and_publishes_in_one_call(): void
    {
        $user = $this->companyUser();

        $response = $this->actingAs($user, 'api')->postJson('/api/job-offers/express', [
            'title' => 'Vendeur conseil en alternance',
            'contract_type' => ContractType::ALTERNANCE->value,
            'postal_code' => '66000',
            'city' => 'Perpignan',
            'sector' => OfferSector::COMMERCE->value,
            'recruitment_radius_km' => 25,
        ])->assertCreated();

        $response->assertJsonPath('data.status', JobOfferStatus::PUBLISHED->value);
        $response->assertJsonPath('data.payment_status', PaymentStatus::FREE->value);
        $response->assertJsonPath('data.recruitment_radius_km', 25);

        $offer = JobOffer::firstOrFail();
        $this->assertNotNull($offer->published_at);
        $this->assertNotNull($offer->applications_unlocked_at);
        // Geolocalisee des la creation, sinon elle n'entrerait dans aucune pile.
        $this->assertSame(42.6887, $offer->latitude);
        $this->assertStringContainsString('à compléter', $offer->description);
    }

    public function test_express_refuses_a_postal_code_that_is_not_five_digits(): void
    {
        $this->actingAs($this->companyUser(), 'api')->postJson('/api/job-offers/express', [
            'title' => 'Vendeur conseil',
            'contract_type' => ContractType::ALTERNANCE->value,
            'postal_code' => '66',
            'city' => 'Perpignan',
            'sector' => OfferSector::COMMERCE->value,
        ])->assertStatus(400);

        $this->assertDatabaseCount('job_offers', 0);
    }

    public function test_express_refuses_an_explicit_null_radius_instead_of_crashing(): void
    {
        // recruitment_radius_km a un defaut en base et n'accepte pas NULL.
        // Avec la regle « nullable », un null explicite passait la
        // validation et faisait echouer l'insertion : une 500 la ou la
        // bonne reponse est « champ invalide ».
        $this->actingAs($this->companyUser(), 'api')->postJson('/api/job-offers/express', [
            'title' => 'Vendeur conseil',
            'contract_type' => ContractType::ALTERNANCE->value,
            'postal_code' => '66000',
            'city' => 'Perpignan',
            'sector' => OfferSector::COMMERCE->value,
            'recruitment_radius_km' => null,
        ])->assertStatus(400)->assertJsonPath('error.code', 'INVALID_INPUT');

        $this->assertDatabaseCount('job_offers', 0);
    }

    public function test_a_draft_refuses_an_explicit_null_on_a_defaulted_column(): void
    {
        $user = $this->companyUser();

        foreach (['recruitment_radius_km', 'minimum_age', 'requires_driving_license'] as $champ) {
            $this->actingAs($user, 'api')->postJson('/api/job-offers', [
                'title' => 'Vendeur', 'description' => 'x',
                'contract_type' => ContractType::ALTERNANCE->value,
                $champ => null,
            ])->assertStatus(400, "Champ : {$champ}");
        }

        $this->assertDatabaseCount('job_offers', 0);
    }

    public function test_express_leaves_no_ghost_draft_when_free_mode_is_off(): void
    {
        // La transaction existe pour cela : une offre creee mais non publiee
        // serait un brouillon que personne n'a demande.
        Config::set('services.jeuncy.gratuit', false);

        $this->actingAs($this->companyUser(), 'api')->postJson('/api/job-offers/express', [
            'title' => 'Vendeur conseil',
            'contract_type' => ContractType::ALTERNANCE->value,
            'postal_code' => '66000',
            'city' => 'Perpignan',
            'sector' => OfferSector::COMMERCE->value,
        ])->assertStatus(409)->assertJsonPath('error.code', 'FREE_PUBLICATION_DISABLED');

        $this->assertDatabaseCount('job_offers', 0);
    }

    public function test_publish_requires_postal_code_with_organization_fallback(): void
    {
        $service = $this->app->make(JobOfferService::class);

        // L'entreprise a un code postal : l'offre en herite.
        $withOrganizationCode = $this->companyUser(['postal_code' => '66000', 'city' => 'Perpignan']);
        $offer = $service->createForUser($withOrganizationCode, [
            'title' => 'Vendeur', 'description' => 'x', 'contract_type' => ContractType::ALTERNANCE->value,
        ]);

        $published = $service->publishFreeForUser($withOrganizationCode->fresh(), $offer);

        $this->assertSame('66000', $published->postal_code);
        $this->assertSame(42.6887, $published->fresh()->latitude);

        // Aucun code postal nulle part : la publication est refusee, en
        // disant quoi faire.
        $sansCode = $this->companyUser(['postal_code' => null, 'city' => null]);
        $orpheline = $service->createForUser($sansCode, [
            'title' => 'Vendeur', 'description' => 'x', 'contract_type' => ContractType::ALTERNANCE->value,
        ]);

        $this->actingAs($sansCode->fresh(), 'api')
            ->postJson("/api/job-offers/{$orpheline->id}/publish")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'JOB_OFFER_POSTAL_CODE_REQUIRED');

        $this->assertSame(JobOfferStatus::DRAFT, $orpheline->fresh()->status);
    }

    public function test_update_allows_published_free_offer(): void
    {
        // Le cas reel : l'offre publiee d'IDA n'a pas de code postal et doit
        // pouvoir en recevoir un sans etre depubliee.
        $user = $this->companyUser();
        $offer = JobOffer::factory()->published()->create([
            'company_id' => $user->company->id,
            'postal_code' => null,
        ]);

        $this->actingAs($user, 'api')
            ->patchJson("/api/job-offers/{$offer->id}", ['postal_code' => '66000', 'city' => 'Perpignan'])
            ->assertOk()
            ->assertJsonPath('data.postal_code', '66000');

        $offer->refresh();
        $this->assertSame(JobOfferStatus::PUBLISHED, $offer->status, 'La modification ne depublie pas.');
        $this->assertNotNull($offer->published_at);
        $this->assertSame(42.6887, $offer->latitude, 'Le nouveau code postal est geocode.');
    }

    public function test_update_still_refuses_a_paid_published_offer(): void
    {
        $user = $this->companyUser();
        $offer = JobOffer::factory()->create([
            'company_id' => $user->company->id,
            'status' => JobOfferStatus::PUBLISHED,
            'payment_status' => PaymentStatus::SUCCEEDED,
        ]);

        $this->actingAs($user, 'api')
            ->patchJson("/api/job-offers/{$offer->id}", ['title' => 'Nouveau titre'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'JOB_OFFER_NOT_DRAFT');
    }

    public function test_archive_and_delete_close_interests(): void
    {
        $service = $this->app->make(JobOfferService::class);
        $user = $this->companyUser();

        $archivee = JobOffer::factory()->published()->create(['company_id' => $user->company->id]);
        $supprimee = JobOffer::factory()->published()->create(['company_id' => $user->company->id]);

        $interetArchive = OfferInterest::factory()->matched()->create([
            'candidate_profile_id' => CandidateProfile::factory()->adult()->create()->id,
            'job_offer_id' => $archivee->id,
        ]);
        $interetSupprime = OfferInterest::factory()->matched()->create([
            'candidate_profile_id' => CandidateProfile::factory()->adult()->create()->id,
            'job_offer_id' => $supprimee->id,
        ]);

        $service->archiveForUser($user->fresh(), $archivee);
        $service->deleteForUser($user->fresh(), $supprimee);

        $interetArchive->refresh();
        $this->assertNotNull($interetArchive->closed_at);
        $this->assertSame(MatchClosedReason::OFFER_ARCHIVED, $interetArchive->closed_reason);

        // La ligne part en cascade avec l'offre : ce qui se verifie, c'est
        // que la fermeture (et sa notification) a eu lieu AVANT.
        $this->assertDatabaseMissing('offer_interests', ['id' => $interetSupprime->id]);
        $this->assertDatabaseHas('notifications', ['type' => 'MATCH_CLOSED']);
    }

    public function test_role_guard_still_applies_to_express(): void
    {
        $candidate = User::create(['email' => 'lea@example.com', 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);

        $this->actingAs($candidate, 'api')->postJson('/api/job-offers/express', [
            'title' => 'Vendeur', 'contract_type' => ContractType::ALTERNANCE->value,
            'postal_code' => '66000', 'city' => 'Perpignan', 'sector' => OfferSector::COMMERCE->value,
        ])->assertStatus(403);
    }
}
