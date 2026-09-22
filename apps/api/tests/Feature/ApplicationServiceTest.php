<?php

namespace Tests\Feature;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\ContractType;
use App\Enums\InterestDecision;
use App\Enums\JobOfferStatus;
use App\Enums\MatchClosedReason;
use App\Enums\NotificationType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Exceptions\ApiException;
use App\Models\CandidateProfile;
use App\Models\GeneratedCv;
use App\Models\JobOffer;
use App\Models\Notification;
use App\Models\OfferInterest;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ApplicationService;
use App\Services\CandidateProfileService;
use App\Services\CompanyService;
use App\Services\DiscoverService;
use App\Services\JobOfferService;
use App\Services\MailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
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

    private function makeCv(CandidateProfile $profile, $archivedAt = null): GeneratedCv
    {
        return $profile->generatedCvs()->create([
            'file_url' => 'https://example.test/cv.pdf',
            'archived_at' => $archivedAt,
        ]);
    }

    private function makePublishedOffer(string $companyEmail = 'rh@nexatech.example.com'): JobOffer
    {
        $owner = User::create(['email' => $companyEmail, 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
        $company = $this->companyService->createForUser($owner, ['name' => 'NexaTech']);
        // Depuis le 2026-09-22, listForOffer exige une entreprise VERIFIED :
        // « jamais d'employeur non verifie face a un candidat » est un
        // invariant, pas une option. Les tests d'acces payant gardent leur
        // sens avec une entreprise verifiee mais sans abonnement.
        $company->forceFill([
            'verification_status' => VerificationStatus::VERIFIED,
            'verified_at' => now(),
        ])->saveQuietly();
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

    public function test_apply_stores_contact_phone_and_generated_cv(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();
        $cv = $this->makeCv($candidate->candidateProfile);

        $application = $this->service->applyForUser($candidate, $offer, null, '0612345678', $cv->id);

        $this->assertSame('0612345678', $application->contact_phone);
        $this->assertSame($cv->id, $application->generated_cv_id);
    }

    public function test_apply_stores_uploaded_cv_file(): void
    {
        Storage::fake('public');
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();
        $file = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');

        $application = $this->service->applyForUser($candidate, $offer, null, '0612345678', null, $file);

        $this->assertNull($application->generated_cv_id);
        $this->assertNotNull($application->cv_file_url);
        Storage::disk('public')->assertExists('application-cvs/'.basename($application->cv_file_url));
    }

    public function test_apply_prefers_uploaded_file_over_generated_cv_id(): void
    {
        Storage::fake('public');
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();
        $cv = $this->makeCv($candidate->candidateProfile);
        $file = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');

        $application = $this->service->applyForUser($candidate, $offer, null, '0612345678', $cv->id, $file);

        $this->assertNull($application->generated_cv_id);
        $this->assertNotNull($application->cv_file_url);
    }

    public function test_apply_rejects_cv_owned_by_another_candidate(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate('lea@example.com');
        $otherCandidate = $this->makeCandidate('malik@example.com');
        $otherCv = $this->makeCv($otherCandidate->candidateProfile);

        $this->expectException(ApiException::class);
        $this->service->applyForUser($candidate, $offer, null, '0612345678', $otherCv->id);
    }

    public function test_apply_rejects_archived_cv(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();
        $archivedCv = $this->makeCv($candidate->candidateProfile, now());

        $this->expectException(ApiException::class);
        $this->service->applyForUser($candidate, $offer, null, '0612345678', $archivedCv->id);
    }

    public function test_list_for_offer_exposes_candidate_email_and_cv(): void
    {
        $offer = $this->makePublishedOffer();
        $offer->update(['applications_unlocked_at' => now()]);
        $candidate = $this->makeCandidate('lea@example.com');
        $cv = $this->makeCv($candidate->candidateProfile);
        $this->service->applyForUser($candidate, $offer, null, '0612345678', $cv->id);

        $owner = $offer->company->user;
        $applications = $this->service->listForOffer($owner->fresh(), $offer->fresh());

        $this->assertSame('lea@example.com', $applications->first()->candidateProfile->user->email);
        $this->assertSame($cv->id, $applications->first()->generatedCv->id);
    }

    // Nouveau modele economique du 2026-08-05 : sans deblocage a l'offre ni
    // abonnement actif, la liste des candidatures est desormais payante (402).
    public function test_list_for_offer_requires_applications_access(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate('lea@example.com');
        $cv = $this->makeCv($candidate->candidateProfile);
        $this->service->applyForUser($candidate, $offer, null, '0612345678', $cv->id);

        $owner = $offer->company->user;

        $this->expectException(ApiException::class);
        $this->service->listForOffer($owner->fresh(), $offer->fresh());
    }

    public function test_list_for_offer_allowed_with_active_subscription(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate('lea@example.com');
        $cv = $this->makeCv($candidate->candidateProfile);
        $this->service->applyForUser($candidate, $offer, null, '0612345678', $cv->id);

        $owner = $offer->company->user;
        Subscription::create([
            'user_id' => $owner->id,
            'status' => SubscriptionStatus::ACTIVE,
            'amount_cents' => 7900,
            'stripe_subscription_id' => 'sub_test_'.$owner->id,
            'stripe_customer_id' => 'cus_test_'.$owner->id,
        ]);

        $applications = $this->service->listForOffer($owner->fresh(), $offer->fresh());

        $this->assertCount(1, $applications);
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

    public function test_withdraw_deletes_application_and_notifies_owner(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();
        $application = $this->service->applyForUser($candidate, $offer, null);
        $applicationId = $application->id;

        $this->service->withdrawForUser($candidate->fresh(), $application);

        $this->assertDatabaseMissing('applications', ['id' => $applicationId]);
        $owner = User::where('email', 'rh@nexatech.example.com')->first();
        $this->assertSame(1, $owner->notifications()->where('type', NotificationType::APPLICATION_STATUS_CHANGED)->count());
    }

    public function test_withdraw_rejects_application_owned_by_another_candidate(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();
        $application = $this->service->applyForUser($candidate, $offer, null);
        $intruder = $this->makeCandidate('malik@example.com');

        $this->expectException(ApiException::class);
        $this->service->withdrawForUser($intruder->fresh(), $application);
    }

    public function test_withdraw_deletes_uploaded_cv_file(): void
    {
        Storage::fake('public');
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();
        $file = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');
        $application = $this->service->applyForUser($candidate, $offer, null, '0612345678', null, $file);
        $storedPath = 'application-cvs/'.basename($application->cv_file_url);

        $this->service->withdrawForUser($candidate->fresh(), $application);

        Storage::disk('public')->assertMissing($storedPath);
    }

    public function test_withdraw_allows_reapplying_to_same_offer(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();
        $application = $this->service->applyForUser($candidate, $offer, null);

        $this->service->withdrawForUser($candidate->fresh(), $application);
        $newApplication = $this->service->applyForUser($candidate->fresh(), $offer->fresh(), 'Je retente ma chance !');

        $this->assertNotNull($newApplication->id);
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

    // Remplace MailService par un mock ET reconstruit le service : ses
    // dependances sont injectees au constructeur, un binding pose apres coup
    // n'aurait aucun effet sur une instance deja resolue.
    private function expectMail(string $method, int $times): void
    {
        $mock = Mockery::mock(MailService::class);
        $mock->shouldReceive($method)->times($times);
        $mock->shouldIgnoreMissing();
        $this->app->instance(MailService::class, $mock);
        $this->service = $this->app->make(ApplicationService::class);
    }

    // La notification in-app suppose que le recruteur se connecte pour la
    // voir. L'email est ce qui le fait revenir — sans lui, une candidature
    // peut dormir des jours.
    public function test_new_application_emails_the_offer_owner(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();

        $this->expectMail('sendNewApplicationEmail', 1);

        $this->service->applyForUser($candidate, $offer, null);
    }

    public function test_status_change_emails_the_candidate(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();
        $application = $this->service->applyForUser($candidate, $offer, null);

        $this->expectMail('sendApplicationStatusChangedEmail', 1);

        $this->service->updateStatus($offer->company->user, $application, ApplicationStatus::INTERVIEW);
    }

    // L'email ne doit jamais faire echouer la candidature : c'est un a-cote,
    // pas une etape du parcours. Si l'envoi casse, la candidature reste
    // enregistree et la notification in-app joue son role de repli.
    public function test_application_still_succeeds_when_mail_sending_fails(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();

        $mock = Mockery::mock(MailService::class);
        $mock->shouldReceive('sendNewApplicationEmail')
            ->andThrow(new \RuntimeException('Resend indisponible'));
        $mock->shouldIgnoreMissing();
        $this->app->instance(MailService::class, $mock);
        $service = $this->app->make(ApplicationService::class);

        $application = $service->applyForUser($candidate, $offer, null);

        $this->assertNotNull($application->id);
        $this->assertDatabaseHas('applications', ['id' => $application->id]);
    }

    // ---------------------------------------------------------------------
    // Modele match (2026-09-22). UN DOSSIER VAUT « Ca m'interesse » : sans
    // cela, une offre a laquelle le candidat vient de postuler resterait
    // dans sa pile Decouvrir, et un match ne d'un dossier n'existerait
    // jamais comme match.
    // ---------------------------------------------------------------------

    public function test_apply_sets_source_site_app_or_match(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();

        $depuisLeSite = $this->service->applyForUser($candidate, $offer, null);
        $this->assertSame(ApplicationSource::SITE, $depuisLeSite->source);

        $offer2 = $this->makePublishedOffer('rh2@nexatech.example.com');
        $depuisLApp = $this->service->applyForUser($candidate, $offer2, null, null, null, null, fromMobile: true);
        $this->assertSame(ApplicationSource::APP, $depuisLApp->source);

        // Un match prime sur le client : ce qui compte pour lire les
        // chiffres, c'est qu'un match ait precede le dossier.
        $offer3 = $this->makePublishedOffer('rh3@nexatech.example.com');
        OfferInterest::create([
            'candidate_profile_id' => $candidate->candidateProfile->id,
            'job_offer_id' => $offer3->id,
            'candidate_decision' => InterestDecision::LIKE,
            'employer_decision' => InterestDecision::LIKE,
            'matched_at' => now(),
        ]);
        $apresMatch = $this->service->applyForUser($candidate, $offer3, null, null, null, null, fromMobile: true);
        $this->assertSame(ApplicationSource::MATCH, $apresMatch->source);
    }

    public function test_apply_links_interest_both_ways(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();

        $application = $this->service->applyForUser($candidate, $offer, null);
        $interest = OfferInterest::firstOrFail();

        $this->assertSame($interest->id, $application->fresh()->interest_id);
        $this->assertSame($application->id, $interest->application_id);
        $this->assertSame(InterestDecision::LIKE, $interest->candidate_decision);
    }

    public function test_apply_records_candidate_like_and_matches_silently_when_employer_liked_first(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();

        OfferInterest::create([
            'candidate_profile_id' => $candidate->candidateProfile->id,
            'job_offer_id' => $offer->id,
            'employer_decision' => InterestDecision::LIKE,
            'employer_decided_at' => now(),
        ]);

        $application = $this->service->applyForUser($candidate, $offer, null);
        $interest = OfferInterest::firstOrFail();

        $this->assertNotNull($interest->matched_at);
        $this->assertSame($application->id, $interest->application_id);
        // Silencieux : la notification NEW_APPLICATION dit deja la meme
        // chose a l'employeur, un NEW_MATCH en plus la dirait deux fois.
        $this->assertSame(0, Notification::where('type', NotificationType::NEW_MATCH)->count());
        $this->assertNotNull($interest->candidate_notified_at);
    }

    public function test_a_previous_pass_gives_way_to_an_application(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();

        OfferInterest::create([
            'candidate_profile_id' => $candidate->candidateProfile->id,
            'job_offer_id' => $offer->id,
            'candidate_decision' => InterestDecision::PASS,
            'candidate_decided_at' => now(),
        ]);

        // Passer puis postuler n'est pas une contradiction a refuser : c'est
        // un avis qui a evolue, et postuler est le geste le plus fort.
        $this->service->applyForUser($candidate, $offer, null);

        $this->assertSame(InterestDecision::LIKE, OfferInterest::firstOrFail()->candidate_decision);
    }

    public function test_applied_offer_leaves_the_candidate_pile(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();

        $this->service->applyForUser($candidate, $offer, null);

        $pile = $this->app->make(DiscoverService::class)
            ->offersForCandidate($candidate->fresh());

        $this->assertSame([], array_column($pile['jeuncy'], 'id'));
    }

    public function test_update_status_sets_responded_at_once(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();
        $owner = $this->jobOfferService->ownerUser($offer);
        $application = $this->service->applyForUser($candidate, $offer, null);

        $this->service->updateStatus($owner, $application, ApplicationStatus::SEEN);
        $premiereReponse = $application->fresh()->responded_at;
        $this->assertNotNull($premiereReponse);

        $this->travel(2)->days();
        $this->service->updateStatus($owner, $application->fresh(), ApplicationStatus::REJECTED);

        // La promesse « reponse garantie » se mesure a la PREMIERE reponse :
        // un changement de statut ulterieur ne doit pas repousser la date.
        $this->assertEquals($premiereReponse, $application->fresh()->responded_at);
        $this->travelBack();
    }

    public function test_withdraw_closes_match_and_notifies_employer(): void
    {
        $offer = $this->makePublishedOffer();
        $candidate = $this->makeCandidate();
        $owner = $this->jobOfferService->ownerUser($offer);

        OfferInterest::create([
            'candidate_profile_id' => $candidate->candidateProfile->id,
            'job_offer_id' => $offer->id,
            'employer_decision' => InterestDecision::LIKE,
            'employer_decided_at' => now(),
        ]);
        $application = $this->service->applyForUser($candidate, $offer, null);
        $this->assertNotNull(OfferInterest::firstOrFail()->matched_at);

        $this->service->withdrawForUser($candidate, $application);

        $interest = OfferInterest::firstOrFail();
        $this->assertNotNull($interest->closed_at);
        $this->assertSame(MatchClosedReason::APPLICATION_WITHDRAWN, $interest->closed_reason);
        $this->assertSame(1, Notification::where('user_id', $owner->id)
            ->where('type', NotificationType::MATCH_CLOSED)->count());
    }

    public function test_list_for_offer_requires_verified_company(): void
    {
        $offer = $this->makePublishedOffer();
        $owner = $this->jobOfferService->ownerUser($offer);
        $owner->company->forceFill(['verification_status' => VerificationStatus::PENDING])->saveQuietly();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage("Ton entreprise doit être vérifiée avant d'accéder aux candidats.");

        $this->service->listForOffer($owner->fresh(), $offer);
    }
}
