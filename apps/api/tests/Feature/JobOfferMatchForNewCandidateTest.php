<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\JobOffer;
use App\Models\Notification;
use App\Models\User;
use App\Services\CandidateProfileService;
use App\Services\JobOfferMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Sens inverse de la correspondance : le candidat qui ARRIVE apres l'offre.
//
// Constate en production le 2026-09-04 : une offre etait publiee, un compte
// candidat correspondant a ete cree ensuite, et rien n'est arrive. La
// notification ne partait qu'a la publication, donc un candidat inscrit apres
// n'entendait jamais parler des offres deja en ligne — precisement le cas
// courant quand l'equipe remplit la CVtheque par prospection telephonique.
class JobOfferMatchForNewCandidateTest extends TestCase
{
    use RefreshDatabase;

    private JobOfferMatchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(JobOfferMatchService::class);
    }

    private function publishOffer(array $overrides = []): JobOffer
    {
        static $n = 0;
        $n++;

        $owner = User::create([
            'email' => "rh{$n}@nexatech.example.com",
            'password_hash' => 'x',
            'role' => UserRole::COMPANY,
        ]);
        $company = Company::create([
            'user_id' => $owner->id,
            'name' => 'NexaTech',
            'siret' => str_pad((string) $n, 14, '0', STR_PAD_LEFT),
        ]);

        return JobOffer::create(array_merge([
            'company_id' => $company->id,
            'title' => 'Négociateur technico commercial',
            'description' => 'Prospection et vente.',
            'contract_type' => ContractType::ALTERNANCE,
            'status' => JobOfferStatus::PUBLISHED,
            'payment_status' => PaymentStatus::SUCCEEDED,
            'city' => 'Perpignan',
            'published_at' => now(),
        ], $overrides));
    }

    private function makeProfile(array $overrides = []): CandidateProfile
    {
        static $n = 0;
        $n++;

        $user = User::create([
            'email' => "candidat{$n}@example.com",
            'password_hash' => 'x',
            'role' => UserRole::CANDIDATE,
        ]);

        return CandidateProfile::create(array_merge([
            'user_id' => $user->id,
            'first_name' => 'Lea',
            'last_name' => 'Girard',
        ], $overrides));
    }

    // --- Le cas reel ---

    public function test_a_candidate_arriving_after_the_offer_is_notified(): void
    {
        $offre = $this->publishOffer();
        $profile = $this->makeProfile(['headline' => 'Recherche une alternance de négociateur']);

        $this->assertSame(1, $this->service->notifyCandidateOfMatchingOffers($profile));

        $notif = Notification::first();
        $this->assertSame($profile->user_id, $notif->user_id);
        $this->assertSame('/offres/'.$offre->id, $notif->link);
        $this->assertStringContainsString('Postule en un clic', $notif->message);
    }

    // Le message differe de celui de la publication : ici l'offre n'est pas
    // nouvelle, c'est le candidat qui vient d'arriver.
    public function test_the_message_does_not_claim_the_offer_is_new(): void
    {
        $this->publishOffer();
        $profile = $this->makeProfile(['city' => 'Perpignan']);

        $this->service->notifyCandidateOfMatchingOffers($profile);

        $this->assertStringNotContainsString("vient d'être publiée", Notification::first()->message);
        $this->assertStringContainsString('correspond à ton profil', Notification::first()->message);
    }

    // --- Ce qui ne doit pas arriver ---

    // La methode est appelee a chaque modification de profil : sans
    // deduplication, un candidat recevrait la meme offre a chaque
    // enregistrement.
    public function test_the_same_offer_is_never_announced_twice(): void
    {
        $this->publishOffer();
        $profile = $this->makeProfile(['city' => 'Perpignan']);

        $this->assertSame(1, $this->service->notifyCandidateOfMatchingOffers($profile));
        $this->assertSame(0, $this->service->notifyCandidateOfMatchingOffers($profile));
        $this->assertSame(0, $this->service->notifyCandidateOfMatchingOffers($profile));

        $this->assertSame(1, Notification::count());
    }

    public function test_a_draft_offer_is_never_announced(): void
    {
        $this->publishOffer(['status' => JobOfferStatus::DRAFT, 'published_at' => null]);
        $profile = $this->makeProfile(['city' => 'Perpignan']);

        $this->assertSame(0, $this->service->notifyCandidateOfMatchingOffers($profile));
    }

    public function test_an_unrelated_candidate_is_not_notified(): void
    {
        $this->publishOffer();
        $profile = $this->makeProfile(['city' => 'Lille', 'headline' => 'Développeur web']);

        $this->assertSame(0, $this->service->notifyCandidateOfMatchingOffers($profile));
    }

    public function test_an_offer_already_applied_to_is_not_announced(): void
    {
        $offre = $this->publishOffer();
        $profile = $this->makeProfile(['city' => 'Perpignan']);
        $profile->applications()->create([
            'job_offer_id' => $offre->id,
            'status' => ApplicationStatus::SENT,
            'contact_phone' => '0600000000',
        ]);

        $this->assertSame(0, $this->service->notifyCandidateOfMatchingOffers($profile));
    }

    // Quelqu'un qui complete un profil riche peut correspondre a beaucoup
    // d'offres : lui en envoyer quinze d'affilee serait du harcelement.
    public function test_at_most_three_offers_are_announced_at_once(): void
    {
        foreach (range(1, 6) as $i) {
            $this->publishOffer(['title' => "Négociateur technico commercial {$i}"]);
        }
        $profile = $this->makeProfile(['city' => 'Perpignan']);

        $this->assertSame(3, $this->service->notifyCandidateOfMatchingOffers($profile));
        $this->assertSame(3, Notification::count());
    }

    // --- Le branchement reel ---

    public function test_creating_a_profile_triggers_the_notification(): void
    {
        $this->publishOffer();
        $user = User::create([
            'email' => 'nouveau@example.com',
            'password_hash' => 'x',
            'role' => UserRole::CANDIDATE,
        ]);

        $this->app->make(CandidateProfileService::class)->createForUser($user, [
            'first_name' => 'Lea',
            'last_name' => 'Girard',
            'city' => 'Perpignan',
        ]);

        $this->assertSame(1, Notification::where('type', NotificationType::JOB_OFFER_MATCH)->count());
    }

    // Le profil peut etre cree vide puis complete : sans ce branchement-la, un
    // candidat qui renseigne sa ville dans un second temps ne recevrait rien.
    public function test_updating_a_profile_triggers_the_notification(): void
    {
        $this->publishOffer();
        $user = User::create([
            'email' => 'nouveau@example.com',
            'password_hash' => 'x',
            'role' => UserRole::CANDIDATE,
        ]);
        $service = $this->app->make(CandidateProfileService::class);
        $service->createForUser($user, ['first_name' => 'Lea', 'last_name' => 'Girard']);

        $this->assertSame(0, Notification::count());

        $service->updateForUser($user->fresh(), ['city' => 'Perpignan']);

        $this->assertSame(1, Notification::where('type', NotificationType::JOB_OFFER_MATCH)->count());
    }

    // Une competence peut suffire a faire correspondre un profil : elle est
    // enregistree par un endpoint distinct des informations personnelles.
    public function test_syncing_skills_triggers_the_notification(): void
    {
        $this->publishOffer(['title' => 'Chargé de prospection', 'city' => 'Lyon']);
        $user = User::create([
            'email' => 'nouveau@example.com',
            'password_hash' => 'x',
            'role' => UserRole::CANDIDATE,
        ]);
        $service = $this->app->make(CandidateProfileService::class);
        $service->createForUser($user, ['first_name' => 'Lea', 'last_name' => 'Girard']);

        $this->assertSame(0, Notification::count());

        $service->syncSkills($user->fresh(), ['Prospection']);

        $this->assertSame(1, Notification::where('type', NotificationType::JOB_OFFER_MATCH)->count());
    }

    // --- Le rattrapage ---

    // Les candidats deja inscrits avant qu'une offre existe, et qui ne
    // touchent plus a leur profil, ne sont couverts par aucun des deux sens
    // evenementiels : c'est le cas des 54 profils de production.
    public function test_the_sweep_catches_candidates_who_never_touch_their_profile(): void
    {
        $this->makeProfile(['city' => 'Perpignan']);
        $this->makeProfile(['city' => 'Perpignan']);
        $this->makeProfile(['city' => 'Lille', 'headline' => 'Développeur web']);
        $this->publishOffer();

        $this->artisan('job-offers:notify-matching-candidates')->assertSuccessful();

        $this->assertSame(2, Notification::where('type', NotificationType::JOB_OFFER_MATCH)->count());
    }

    // Le cron la relance chaque jour : sans idempotence, chaque candidat
    // recevrait la meme offre tous les matins.
    public function test_the_sweep_is_idempotent(): void
    {
        $this->makeProfile(['city' => 'Perpignan']);
        $this->publishOffer();

        $this->artisan('job-offers:notify-matching-candidates')->assertSuccessful();
        $this->artisan('job-offers:notify-matching-candidates')->assertSuccessful();
        $this->artisan('job-offers:notify-matching-candidates')->assertSuccessful();

        $this->assertSame(1, Notification::where('type', NotificationType::JOB_OFFER_MATCH)->count());
    }
}
