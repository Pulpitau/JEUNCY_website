<?php

namespace Tests\Feature;

use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Enums\MatchClosedReason;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\CandidateProfile;
use App\Models\Notification;
use App\Models\OfferInterest;
use App\Models\User;
use App\Services\CompanyService;
use App\Services\JobOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireJobOffersCommandTest extends TestCase
{
    use RefreshDatabase;

    private JobOfferService $jobOfferService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jobOfferService = $this->app->make(JobOfferService::class);
    }

    private function makeOwner(): User
    {
        $user = User::create(['email' => 'rh@nexatech.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
        $this->app->make(CompanyService::class)->createForUser($user, ['name' => 'NexaTech']);

        return $user->fresh();
    }

    public function test_expires_published_offers_past_their_expiry_date(): void
    {
        $owner = $this->makeOwner();
        $expired = $this->jobOfferService->createForUser($owner, [
            'title' => 'Développeur web en alternance',
            'description' => 'Rejoins notre équipe.',
            'contract_type' => ContractType::ALTERNANCE->value,
        ]);
        $expired->update(['status' => JobOfferStatus::PUBLISHED, 'expires_at' => now()->subDay()]);

        $stillValid = $this->jobOfferService->createForUser($owner, [
            'title' => 'Alternant marketing',
            'description' => 'Rejoins notre équipe marketing.',
            'contract_type' => ContractType::ALTERNANCE->value,
        ]);
        $stillValid->update(['status' => JobOfferStatus::PUBLISHED, 'expires_at' => now()->addDay()]);

        $this->artisan('job-offers:expire')->assertSuccessful();

        $this->assertSame(JobOfferStatus::EXPIRED, $expired->fresh()->status);
        $this->assertSame(JobOfferStatus::PUBLISHED, $stillValid->fresh()->status);
    }

    // Une offre qui sort de la ligne emporte ses matchs. Sans cette
    // fermeture, le candidat garderait une carte « en attente de reponse »
    // sur une offre qui n'existe plus nulle part ailleurs — et il ne
    // l'apprendrait que par le silence.
    public function test_expiry_closes_matches_with_notification(): void
    {
        $owner = $this->makeOwner();
        $offre = $this->jobOfferService->createForUser($owner, [
            'title' => 'Développeur web en alternance',
            'description' => 'Rejoins notre équipe.',
            'contract_type' => ContractType::ALTERNANCE->value,
        ]);
        $offre->update(['status' => JobOfferStatus::PUBLISHED, 'expires_at' => now()->subDay()]);

        $candidat = CandidateProfile::factory()->adult()->create();
        $interet = OfferInterest::factory()->matched()->create([
            'candidate_profile_id' => $candidat->id,
            'job_offer_id' => $offre->id,
        ]);

        $this->artisan('job-offers:expire')->assertSuccessful();

        $interet->refresh();
        $this->assertNotNull($interet->closed_at);
        $this->assertSame(MatchClosedReason::OFFER_EXPIRED, $interet->closed_reason);

        $this->assertTrue(
            Notification::where('user_id', $candidat->user_id)
                ->where('type', NotificationType::MATCH_CLOSED)
                ->exists(),
            'le candidat doit apprendre que son match est clos',
        );
    }
}
