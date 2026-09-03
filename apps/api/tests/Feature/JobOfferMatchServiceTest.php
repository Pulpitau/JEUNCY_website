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
use App\Models\Skill;
use App\Models\User;
use App\Services\JobOfferMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Notification des candidats a la publication d'une offre.
//
// Ce ciblage remplace une demande de candidature AUTOMATIQUE au nom du
// candidat : ce qui compte ici est donc autant ce qui est notifie que ce qui
// ne l'est PAS. Une notification hors sujet detruit la confiance aussi surement
// qu'une candidature non voulue.
class JobOfferMatchServiceTest extends TestCase
{
    use RefreshDatabase;

    private JobOfferMatchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(JobOfferMatchService::class);
    }

    private function makeCandidate(array $overrides = []): CandidateProfile
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

    private function makeOffer(array $overrides = []): JobOffer
    {
        $owner = User::create([
            'email' => 'rh'.uniqid().'@example.com',
            'password_hash' => 'x',
            'role' => UserRole::COMPANY,
        ]);

        $company = Company::create([
            'user_id' => $owner->id,
            'name' => 'NexaTech',
            'siret' => '12345678901234',
        ]);

        return JobOffer::create(array_merge([
            'company_id' => $company->id,
            'title' => 'Vendeur en magasin',
            'description' => 'Accueil et conseil client.',
            'contract_type' => ContractType::ALTERNANCE,
            'status' => JobOfferStatus::DRAFT,
            'payment_status' => PaymentStatus::PENDING,
            'city' => 'Perpignan',
        ], $overrides));
    }

    // --- Ce qui declenche une notification ---

    public function test_a_candidate_in_the_same_city_is_notified(): void
    {
        $candidate = $this->makeCandidate(['city' => 'Perpignan']);

        $notified = $this->service->notifyMatchingCandidates($this->makeOffer());

        $this->assertSame(1, $notified);

        $notification = Notification::first();
        $this->assertSame($candidate->user_id, $notification->user_id);
        $this->assertSame(NotificationType::JOB_OFFER_MATCH, $notification->type);
        $this->assertStringContainsString('Postule en un clic', $notification->message);
        $this->assertStringContainsString('Vendeur en magasin', $notification->message);
    }

    // La ville s'ecrit rarement pareil d'un profil a l'autre.
    public function test_city_comparison_ignores_case_and_accents(): void
    {
        $this->makeCandidate(['city' => 'PERPIGNAN']);

        $this->assertSame(1, $this->service->notifyMatchingCandidates($this->makeOffer()));
    }

    // Un candidat d'une autre ville reste notifie si son profil recoupe
    // l'intitule : la mobilite est frequente a cet age.
    public function test_a_candidate_elsewhere_is_notified_on_a_keyword_match(): void
    {
        $candidate = $this->makeCandidate([
            'city' => 'Narbonne',
            'headline' => 'Recherche un poste de vendeur',
        ]);

        $this->assertSame(1, $this->service->notifyMatchingCandidates($this->makeOffer()));
        $this->assertSame($candidate->user_id, Notification::first()->user_id);
    }

    public function test_a_skill_can_trigger_the_match(): void
    {
        $candidate = $this->makeCandidate(['city' => 'Narbonne']);
        $candidate->skills()->attach(Skill::create(['name' => 'Magasin'])->id);

        $this->assertSame(1, $this->service->notifyMatchingCandidates($this->makeOffer()));
    }

    public function test_the_notification_links_to_the_offer(): void
    {
        $this->makeCandidate(['city' => 'Perpignan']);
        $offer = $this->makeOffer();

        $this->service->notifyMatchingCandidates($offer);

        $this->assertSame('/offres/'.$offer->id, Notification::first()->link);
    }

    // --- Ce qui ne doit PAS declencher de notification ---

    public function test_an_unrelated_candidate_is_not_notified(): void
    {
        $this->makeCandidate([
            'city' => 'Lille',
            'headline' => 'Recherche une alternance en developpement web',
        ]);

        $this->assertSame(0, $this->service->notifyMatchingCandidates($this->makeOffer()));
        $this->assertSame(0, Notification::count());
    }

    // Un candidat qui a ecrit chercher une alternance n'a rien a faire d'une
    // mission de benevolat, meme dans sa ville.
    public function test_an_explicit_contract_preference_excludes_other_contracts(): void
    {
        $this->makeCandidate([
            'city' => 'Perpignan',
            'headline' => 'Recherche une alternance en commerce',
        ]);

        $offer = $this->makeOffer(['contract_type' => ContractType::BENEVOLAT]);

        $this->assertSame(0, $this->service->notifyMatchingCandidates($offer));
    }

    // Un profil qui n'exprime aucune preference reste eligible a tout : sinon
    // les candidats qui n'ont pas rempli ce champ ne verraient jamais rien.
    public function test_a_candidate_without_a_stated_preference_stays_eligible(): void
    {
        $this->makeCandidate(['city' => 'Perpignan']);

        $offer = $this->makeOffer(['contract_type' => ContractType::BENEVOLAT]);

        $this->assertSame(1, $this->service->notifyMatchingCandidates($offer));
    }

    public function test_a_candidate_who_already_applied_is_not_notified(): void
    {
        $candidate = $this->makeCandidate(['city' => 'Perpignan']);
        $offer = $this->makeOffer();

        $candidate->applications()->create([
            'job_offer_id' => $offer->id,
            'status' => ApplicationStatus::SENT,
            'contact_phone' => '0600000000',
        ]);

        $this->assertSame(0, $this->service->notifyMatchingCandidates($offer));
    }

    public function test_a_suspended_account_is_not_notified(): void
    {
        $candidate = $this->makeCandidate(['city' => 'Perpignan']);
        $candidate->user->update(['is_suspended' => true]);

        $this->assertSame(0, $this->service->notifyMatchingCandidates($this->makeOffer()));
    }

    public function test_a_deleted_account_is_not_notified(): void
    {
        $candidate = $this->makeCandidate(['city' => 'Perpignan']);
        $candidate->user->update(['deleted_account_at' => now()]);

        $this->assertSame(0, $this->service->notifyMatchingCandidates($this->makeOffer()));
    }

    // Les mots trop frequents d'un intitule ne doivent pas faire correspondre
    // tout le monde : sans cette liste, "alternance" dans un titre notifiait
    // chaque candidat ayant ecrit "alternance" dans son profil, soit presque
    // tous, et la notification perdait tout credit.
    public function test_common_words_alone_do_not_create_a_match(): void
    {
        $this->makeCandidate([
            'city' => 'Lille',
            'headline' => 'Recherche une alternance',
        ]);

        $offer = $this->makeOffer(['title' => 'Alternance chez nous', 'city' => 'Perpignan']);

        $this->assertSame(0, $this->service->notifyMatchingCandidates($offer));
    }

    // --- Volume ---

    public function test_every_matching_candidate_is_notified_once(): void
    {
        foreach (range(1, 5) as $i) {
            $this->makeCandidate(['city' => 'Perpignan']);
        }

        $this->assertSame(5, $this->service->notifyMatchingCandidates($this->makeOffer()));
        $this->assertSame(5, Notification::count());
    }
}
