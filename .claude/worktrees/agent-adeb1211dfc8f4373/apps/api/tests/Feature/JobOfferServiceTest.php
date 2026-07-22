<?php

namespace Tests\Feature;

use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\CfaOrganizationService;
use App\Services\CompanyService;
use App\Services\JobOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobOfferServiceTest extends TestCase
{
    use RefreshDatabase;

    private JobOfferService $service;

    private CompanyService $companyService;

    private CfaOrganizationService $cfaOrganizationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(JobOfferService::class);
        $this->companyService = $this->app->make(CompanyService::class);
        $this->cfaOrganizationService = $this->app->make(CfaOrganizationService::class);
    }

    private function makeCompanyUser(string $email = 'rh@nexatech.example.com'): User
    {
        $user = User::create(['email' => $email, 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
        $this->companyService->createForUser($user, ['name' => 'NexaTech']);

        return $user->fresh();
    }

    private function makeCfaUser(): User
    {
        $user = User::create(['email' => 'contact@cfa-sup-alternance.example.com', 'password_hash' => 'x', 'role' => UserRole::CFA]);
        $this->cfaOrganizationService->createForUser($user, ['name' => 'CFA Sup Alternance']);

        return $user->fresh();
    }

    private function offerPayload(): array
    {
        return [
            'title' => 'Développeur web full-stack en alternance',
            'description' => 'Rejoins notre équipe.',
            'contract_type' => ContractType::ALTERNANCE->value,
        ];
    }

    public function test_create_for_company_user_sets_company_id_and_draft_status(): void
    {
        $offer = $this->service->createForUser($this->makeCompanyUser(), $this->offerPayload());

        $this->assertNotNull($offer->company_id);
        $this->assertNull($offer->cfa_organization_id);
        $this->assertSame(JobOfferStatus::DRAFT, $offer->status);
    }

    public function test_create_for_cfa_user_sets_cfa_organization_id(): void
    {
        $offer = $this->service->createForUser($this->makeCfaUser(), $this->offerPayload());

        $this->assertNotNull($offer->cfa_organization_id);
        $this->assertNull($offer->company_id);
    }

    public function test_update_rejects_offer_owned_by_another_company(): void
    {
        $owner = $this->makeCompanyUser('rh@nexatech.example.com');
        $offer = $this->service->createForUser($owner, $this->offerPayload());

        $intruder = $this->makeCompanyUser('contact@cafedeslices.example.com');

        $this->expectException(ApiException::class);
        $this->service->updateForUser($intruder, $offer, ['title' => 'Hack']);
    }

    public function test_archive_sets_status_archived(): void
    {
        $owner = $this->makeCompanyUser();
        $offer = $this->service->createForUser($owner, $this->offerPayload());

        $archived = $this->service->archiveForUser($owner->fresh(), $offer);

        $this->assertSame(JobOfferStatus::ARCHIVED, $archived->status);
    }

    public function test_require_owned_draft_offer_rejects_already_published_offer(): void
    {
        $owner = $this->makeCompanyUser();
        $offer = $this->service->createForUser($owner, $this->offerPayload());
        $offer->update(['status' => JobOfferStatus::PUBLISHED]);

        $this->expectException(ApiException::class);
        $this->service->requireOwnedDraftOffer($owner->fresh(), $offer);
    }

    public function test_search_published_only_returns_published_offers(): void
    {
        $owner = $this->makeCompanyUser();
        $draft = $this->service->createForUser($owner, $this->offerPayload());
        $published = $this->service->createForUser($owner->fresh(), [...$this->offerPayload(), 'title' => 'Offre publiée']);
        $published->update(['status' => JobOfferStatus::PUBLISHED, 'published_at' => now()]);

        $results = $this->service->searchPublished([]);

        $this->assertTrue($results->contains('id', $published->id));
        $this->assertFalse($results->contains('id', $draft->id));
    }

    public function test_find_published_throws_for_draft_offer(): void
    {
        $owner = $this->makeCompanyUser();
        $offer = $this->service->createForUser($owner, $this->offerPayload());

        $this->expectException(ApiException::class);
        $this->service->findPublished($offer->id);
    }
}
