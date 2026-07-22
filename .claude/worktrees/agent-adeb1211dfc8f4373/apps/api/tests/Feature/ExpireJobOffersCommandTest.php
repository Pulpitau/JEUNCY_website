<?php

namespace Tests\Feature;

use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Enums\UserRole;
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
}
