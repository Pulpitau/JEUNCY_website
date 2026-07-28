<?php

namespace Tests\Feature;

use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\JobOffer;
use App\Models\User;
use App\Services\CfaOrganizationService;
use App\Services\CompanyService;
use App\Services\JobOfferService;
use App\Services\MailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ArchiveExpiredTrialOffersCommandTest extends TestCase
{
    use RefreshDatabase;

    private JobOfferService $jobOfferService;

    private CompanyService $companyService;

    private CfaOrganizationService $cfaOrganizationService;

    private $mailServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailServiceMock = Mockery::mock(MailService::class);
        $this->app->instance(MailService::class, $this->mailServiceMock);
        // Chaque offre publiee via l'essai declenche l'email de bienvenue au
        // demarrage (voir JobOfferServiceTest) — non pertinent pour ces tests,
        // qui portent sur la fin de l'essai : on l'autorise sans le verifier ici.
        $this->mailServiceMock->shouldReceive('sendTrialStartedEmail')->zeroOrMoreTimes();

        $this->jobOfferService = $this->app->make(JobOfferService::class);
        $this->companyService = $this->app->make(CompanyService::class);
        $this->cfaOrganizationService = $this->app->make(CfaOrganizationService::class);
    }

    private function makeOwner(string $email = 'rh@nexatech.example.com'): User
    {
        $user = User::create(['email' => $email, 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
        $this->companyService->createForUser($user, ['name' => 'NexaTech']);

        return $user->fresh();
    }

    private function makeCfaOwner(): User
    {
        $user = User::create(['email' => 'contact@cfa-sup-alternance.example.com', 'password_hash' => 'x', 'role' => UserRole::CFA]);
        $this->cfaOrganizationService->createForUser($user, ['name' => 'CFA Sup Alternance']);

        return $user->fresh();
    }

    private function makeOffer(User $owner, string $title = 'Développeur web en alternance'): JobOffer
    {
        return $this->jobOfferService->createForUser($owner, [
            'title' => $title,
            'description' => 'Rejoins notre équipe.',
            'contract_type' => ContractType::ALTERNANCE->value,
        ]);
    }

    public function test_archives_trial_offer_once_companys_trial_window_is_over(): void
    {
        $owner = $this->makeOwner();
        $offer = $this->makeOffer($owner);
        $this->jobOfferService->publishViaTrialForUser($owner->fresh(), $offer);

        $company = $this->companyService->requireCompany($owner->fresh());
        $company->trial_started_at = now()->subDays(JobOfferService::TRIAL_DURATION_DAYS + 1);
        $company->save();

        $this->mailServiceMock->shouldReceive('sendTrialEndedEmail')
            ->once()
            ->with('rh@nexatech.example.com', [$offer->title], '9,99 €');

        $this->artisan('job-offers:archive-expired-trials')->assertSuccessful();

        $this->assertSame(JobOfferStatus::ARCHIVED, $offer->fresh()->status);
        $this->assertSame(1, $owner->fresh()->notifications()->where('type', NotificationType::TRIAL_OFFERS_ARCHIVED)->count());
    }

    public function test_archives_trial_offer_once_cfas_trial_window_is_over(): void
    {
        $owner = $this->makeCfaOwner();
        $offer = $this->makeOffer($owner);
        $this->jobOfferService->publishViaTrialForUser($owner->fresh(), $offer);

        $cfaOrganization = $this->cfaOrganizationService->requireCfaOrganization($owner->fresh());
        $cfaOrganization->trial_started_at = now()->subDays(JobOfferService::TRIAL_DURATION_DAYS + 1);
        $cfaOrganization->save();

        $this->mailServiceMock->shouldReceive('sendTrialEndedEmail')
            ->once()
            ->with('contact@cfa-sup-alternance.example.com', [$offer->title], '4,99 €');

        $this->artisan('job-offers:archive-expired-trials')->assertSuccessful();

        $this->assertSame(JobOfferStatus::ARCHIVED, $offer->fresh()->status);
        $this->assertSame(1, $owner->fresh()->notifications()->where('type', NotificationType::TRIAL_OFFERS_ARCHIVED)->count());
    }

    // Le quota actuel (TRIAL_MAX_OFFERS = 1) empeche desormais d'atteindre ce
    // cas via publishViaTrialForUser — les 2 offres TRIAL sont donc creees
    // directement en base pour continuer a couvrir la logique de regroupement
    // (utile si le quota est un jour remonte, voir historique du 2026-07-28).
    public function test_archives_multiple_offers_of_same_owner_with_a_single_email(): void
    {
        $owner = $this->makeOwner();
        $first = $this->makeOffer($owner, 'Développeur web en alternance');
        $first->update(['status' => JobOfferStatus::PUBLISHED, 'payment_status' => PaymentStatus::TRIAL, 'published_at' => now()]);
        $second = $this->makeOffer($owner->fresh(), 'Développeur mobile en alternance');
        $second->update(['status' => JobOfferStatus::PUBLISHED, 'payment_status' => PaymentStatus::TRIAL, 'published_at' => now()]);

        $company = $this->companyService->requireCompany($owner->fresh());
        $company->trial_started_at = now()->subDays(JobOfferService::TRIAL_DURATION_DAYS + 1);
        $company->trial_offers_count = 2;
        $company->save();

        $this->mailServiceMock->shouldReceive('sendTrialEndedEmail')
            ->once()
            ->with('rh@nexatech.example.com', [$first->title, $second->title], '9,99 €');

        $this->artisan('job-offers:archive-expired-trials')->assertSuccessful();

        $this->assertSame(JobOfferStatus::ARCHIVED, $first->fresh()->status);
        $this->assertSame(JobOfferStatus::ARCHIVED, $second->fresh()->status);
        $this->assertSame(2, $owner->fresh()->notifications()->where('type', NotificationType::TRIAL_OFFERS_ARCHIVED)->count());
    }

    public function test_does_not_archive_trial_offer_still_within_window(): void
    {
        $owner = $this->makeOwner();
        $offer = $this->makeOffer($owner);
        $this->jobOfferService->publishViaTrialForUser($owner->fresh(), $offer);

        $this->mailServiceMock->shouldNotReceive('sendTrialEndedEmail');

        $this->artisan('job-offers:archive-expired-trials')->assertSuccessful();

        $this->assertSame(JobOfferStatus::PUBLISHED, $offer->fresh()->status);
    }

    public function test_does_not_archive_paid_offer_even_if_companys_trial_is_old(): void
    {
        $owner = $this->makeOwner();
        $offer = $this->makeOffer($owner);
        $offer->update([
            'status' => JobOfferStatus::PUBLISHED,
            'payment_status' => PaymentStatus::SUCCEEDED,
            'published_at' => now(),
        ]);

        $company = $this->companyService->requireCompany($owner->fresh());
        $company->trial_started_at = now()->subDays(JobOfferService::TRIAL_DURATION_DAYS + 1);
        $company->trial_offers_count = 1;
        $company->save();

        $this->mailServiceMock->shouldNotReceive('sendTrialEndedEmail');

        $this->artisan('job-offers:archive-expired-trials')->assertSuccessful();

        $this->assertSame(JobOfferStatus::PUBLISHED, $offer->fresh()->status);
    }
}
