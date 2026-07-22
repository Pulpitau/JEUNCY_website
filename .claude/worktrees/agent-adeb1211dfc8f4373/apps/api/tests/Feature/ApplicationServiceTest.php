<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\JobOffer;
use App\Models\User;
use App\Services\ApplicationService;
use App\Services\CandidateProfileService;
use App\Services\CompanyService;
use App\Services\JobOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ApplicationService $service;

    private CandidateProfileService $candidateProfileService;

    private CompanyService $companyService;

    private JobOfferService $jobOfferService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(ApplicationService::class);
        $this->candidateProfileService = $this->app->make(CandidateProfileService::class);
        $this->companyService = $this->app->make(CompanyService::class);
        $this->jobOfferService = $this->app->make(JobOfferService::class);
    }

    private function makeCandidate(string $email = 'lea@example.com'): User
    {
        $user = User::create(['email' => $email, 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);
        $this->candidateProfileService->createForUser($user, ['first_name' => 'Léa', 'last_name' => 'Girard']);

        return $user->fresh();
    }

    private function makePublishedOffer(string $companyEmail = 'rh@nexatech.example.com'): JobOffer
    {
        $owner = User::create(['email' => $companyEmail, 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
        $this->companyService->createForUser($owner, ['name' => 'NexaTech']);
        $offer = $this->jobOfferService->createForUser($owner->fresh(), [
            'title' => 'Développeur web full-stack en alternance',
            'description' => 'Rejoins notre équipe.',
            'contract_type' => ContractType::ALTERNANCE->value,
        ]);
        $offer->update(['status' => JobOfferStatus::PUBLISHED, 'published_at' => now()]);

        return $offer->fresh();
    }

    public function test_apply_creates_application_and_notifies_owner(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();

        $application = $this->service->applyForUser($candidate, $offer, 'Motivée !');

        $this->assertSame(ApplicationStatus::SENT, $application->status);
        $owner = $offer->company->user;
        $this->assertSame(1, $owner->notifications()->where('type', NotificationType::NEW_APPLICATION)->count());
    }

    public function test_apply_rejects_unpublished_offer(): void
    {
        $owner = User::create(['email' => 'rh@nexatech.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
        $this->companyService->createForUser($owner, ['name' => 'NexaTech']);
        $draftOffer = $this->jobOfferService->createForUser($owner->fresh(), [
            'title' => 'Offre brouillon',
            'description' => 'Non publiée.',
            'contract_type' => ContractType::ALTERNANCE->value,
        ]);
        $candidate = $this->makeCandidate();

        $this->expectException(ApiException::class);
        $this->service->applyForUser($candidate, $draftOffer, null);
    }

    public function test_apply_rejects_duplicate_application(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();
        $this->service->applyForUser($candidate, $offer, null);

        $this->expectException(ApiException::class);
        $this->service->applyForUser($candidate->fresh(), $offer->fresh(), null);
    }

    public function test_list_for_candidate_returns_own_applications(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();
        $this->service->applyForUser($candidate, $offer, null);

        $applications = $this->service->listForCandidate($candidate->fresh());

        $this->assertCount(1, $applications);
    }

    public function test_list_for_offer_rejects_non_owner(): void
    {
        $offer = $this->makePublishedOffer();
        $intruder = User::create(['email' => 'contact@cafedeslices.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
        $this->companyService->createForUser($intruder, ['name' => 'Café des Lices']);

        $this->expectException(ApiException::class);
        $this->service->listForOffer($intruder->fresh(), $offer);
    }

    public function test_update_status_notifies_candidate(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();
        $application = $this->service->applyForUser($candidate, $offer, null);
        $owner = $offer->company->user;

        $updated = $this->service->updateStatus($owner->fresh(), $application, ApplicationStatus::INTERVIEW);

        $this->assertSame(ApplicationStatus::INTERVIEW, $updated->status);
        $this->assertSame(
            1,
            $candidate->fresh()->notifications()->where('type', NotificationType::APPLICATION_STATUS_CHANGED)->count(),
        );
    }

    public function test_update_status_rejects_non_owner(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();
        $application = $this->service->applyForUser($candidate, $offer, null);

        $intruder = User::create(['email' => 'contact@cafedeslices.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
        $this->companyService->createForUser($intruder, ['name' => 'Café des Lices']);

        $this->expectException(ApiException::class);
        $this->service->updateStatus($intruder->fresh(), $application, ApplicationStatus::SEEN);
    }
}
