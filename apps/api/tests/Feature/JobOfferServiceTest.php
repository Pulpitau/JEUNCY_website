<?php

namespace Tests\Feature;

use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\CfaOrganizationService;
use App\Services\CompanyService;
use App\Services\JobOfferService;
use App\Services\MailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class JobOfferServiceTest extends TestCase
{
    use RefreshDatabase;

    private JobOfferService $service;

    private CompanyService $companyService;

    private CfaOrganizationService $cfaOrganizationService;

    private $mailServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailServiceMock = Mockery::mock(MailService::class);
        $this->app->instance(MailService::class, $this->mailServiceMock);

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

    public function test_update_rejects_already_published_offer(): void
    {
        $owner = $this->makeCompanyUser();
        $offer = $this->service->createForUser($owner, $this->offerPayload());
        $offer->update(['status' => JobOfferStatus::PUBLISHED]);

        $this->expectException(ApiException::class);
        $this->service->updateForUser($owner->fresh(), $offer, ['title' => 'Nouveau titre']);
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

    public function test_create_for_user_syncs_skills(): void
    {
        $offer = $this->service->createForUser(
            $this->makeCompanyUser(),
            [...$this->offerPayload(), 'skills' => ['React', 'Vente', ' React ']],
        );

        $this->assertEqualsCanonicalizing(['React', 'Vente'], $offer->skills->pluck('name')->all());
    }

    public function test_update_replaces_skills(): void
    {
        $owner = $this->makeCompanyUser();
        $offer = $this->service->createForUser($owner, [...$this->offerPayload(), 'skills' => ['React']]);

        $updated = $this->service->updateForUser($owner->fresh(), $offer, ['skills' => ['Vente', 'Relation client']]);

        $this->assertEqualsCanonicalizing(['Vente', 'Relation client'], $updated->skills->pluck('name')->all());
    }

    public function test_update_without_skills_key_leaves_existing_skills_untouched(): void
    {
        $owner = $this->makeCompanyUser();
        $offer = $this->service->createForUser($owner, [...$this->offerPayload(), 'skills' => ['React']]);

        $updated = $this->service->updateForUser($owner->fresh(), $offer, ['title' => 'Nouveau titre']);

        $this->assertEqualsCanonicalizing(['React'], $updated->skills->pluck('name')->all());
    }

    public function test_publish_via_trial_starts_trial_and_publishes_offer(): void
    {
        $owner = $this->makeCompanyUser();
        $offer = $this->service->createForUser($owner, $this->offerPayload());
        $this->mailServiceMock->shouldReceive('sendTrialStartedEmail')
            ->once()
            ->with('rh@nexatech.example.com', 'NexaTech', '9,99 €');

        $published = $this->service->publishViaTrialForUser($owner->fresh(), $offer);

        $this->assertSame(JobOfferStatus::PUBLISHED, $published->status);
        $this->assertSame(PaymentStatus::TRIAL, $published->payment_status);
        $this->assertNotNull($published->published_at);

        $company = $this->companyService->requireCompany($owner->fresh());
        $this->assertNotNull($company->trial_started_at);
        $this->assertSame(1, $company->trial_offers_count);
    }

    // Quota reduit a 1 offre le 2026-07-28 (demande explicite) : une deuxieme
    // offre ne peut plus etre publiee via l'essai, meme dans la fenetre de 15
    // jours — l'entreprise doit payer pour celle-ci.
    public function test_publish_via_trial_rejects_second_offer_once_quota_reached(): void
    {
        $owner = $this->makeCompanyUser();
        $this->mailServiceMock->shouldReceive('sendTrialStartedEmail')->once();

        $first = $this->service->createForUser($owner, $this->offerPayload());
        $this->service->publishViaTrialForUser($owner->fresh(), $first);

        $second = $this->service->createForUser($owner->fresh(), [...$this->offerPayload(), 'title' => 'Deuxième offre']);

        $this->expectException(ApiException::class);
        $this->service->publishViaTrialForUser($owner->fresh(), $second);
    }

    public function test_publish_via_trial_allows_cfa_user(): void
    {
        $owner = $this->makeCfaUser();
        $offer = $this->service->createForUser($owner, $this->offerPayload());
        $this->mailServiceMock->shouldReceive('sendTrialStartedEmail')
            ->once()
            ->with('contact@cfa-sup-alternance.example.com', 'CFA Sup Alternance', '4,99 €');

        $published = $this->service->publishViaTrialForUser($owner->fresh(), $offer);

        $this->assertSame(JobOfferStatus::PUBLISHED, $published->status);
        $this->assertSame(PaymentStatus::TRIAL, $published->payment_status);

        $cfaOrganization = $this->cfaOrganizationService->requireCfaOrganization($owner->fresh());
        $this->assertNotNull($cfaOrganization->trial_started_at);
        $this->assertSame(1, $cfaOrganization->trial_offers_count);
    }

    public function test_price_cents_for_differs_between_company_and_cfa(): void
    {
        $companyOffer = $this->service->createForUser($this->makeCompanyUser(), $this->offerPayload());
        $cfaOffer = $this->service->createForUser($this->makeCfaUser(), $this->offerPayload());

        $this->assertSame(999, $this->service->priceCentsFor($companyOffer));
        $this->assertSame(499, $this->service->priceCentsFor($cfaOffer));
        $this->assertSame('9,99 €', $this->service->priceLabelFor($companyOffer));
        $this->assertSame('4,99 €', $this->service->priceLabelFor($cfaOffer));
    }

    public function test_publish_via_trial_rejects_when_offer_quota_exhausted(): void
    {
        $owner = $this->makeCompanyUser();
        $company = $this->companyService->requireCompany($owner);
        $company->trial_started_at = now();
        $company->trial_offers_count = JobOfferService::TRIAL_MAX_OFFERS;
        $company->save();

        $offer = $this->service->createForUser($owner->fresh(), $this->offerPayload());

        $this->expectException(ApiException::class);
        $this->service->publishViaTrialForUser($owner->fresh(), $offer);
    }

    public function test_publish_via_trial_rejects_when_window_expired(): void
    {
        $owner = $this->makeCompanyUser();
        $company = $this->companyService->requireCompany($owner);
        $company->trial_started_at = now()->subDays(JobOfferService::TRIAL_DURATION_DAYS + 1);
        $company->trial_offers_count = 1;
        $company->save();

        $offer = $this->service->createForUser($owner->fresh(), $this->offerPayload());

        $this->expectException(ApiException::class);
        $this->service->publishViaTrialForUser($owner->fresh(), $offer);
    }

    public function test_require_payable_offer_accepts_draft(): void
    {
        $owner = $this->makeCompanyUser();
        $offer = $this->service->createForUser($owner, $this->offerPayload());

        $payable = $this->service->requirePayableOffer($owner->fresh(), $offer);

        $this->assertSame($offer->id, $payable->id);
    }

    public function test_require_payable_offer_accepts_archived_trial_offer(): void
    {
        $owner = $this->makeCompanyUser();
        $offer = $this->service->createForUser($owner, $this->offerPayload());
        $this->mailServiceMock->shouldReceive('sendTrialStartedEmail')->once();
        $this->service->publishViaTrialForUser($owner->fresh(), $offer);
        $offer->update(['status' => JobOfferStatus::ARCHIVED]);

        $payable = $this->service->requirePayableOffer($owner->fresh(), $offer->fresh());

        $this->assertSame(JobOfferStatus::ARCHIVED, $payable->status);
    }

    public function test_require_payable_offer_rejects_manually_archived_paid_offer(): void
    {
        $owner = $this->makeCompanyUser();
        $offer = $this->service->createForUser($owner, $this->offerPayload());
        $offer->update([
            'status' => JobOfferStatus::ARCHIVED,
            'payment_status' => PaymentStatus::SUCCEEDED,
        ]);

        $this->expectException(ApiException::class);
        $this->service->requirePayableOffer($owner->fresh(), $offer->fresh());
    }
}
