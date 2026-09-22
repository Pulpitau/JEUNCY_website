<?php

namespace Tests\Feature;

use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\User;
use App\Services\CfaOrganizationService;
use App\Services\CompanyService;
use App\Services\JobOfferService;
use App\Services\MailService;
use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

/**
 * Jeuncy gratuit pour les entreprises (decision du 2026-09-15).
 *
 * Le reste de la suite tourne avec la gratuite ETEINTE (voir phpunit.xml)
 * pour continuer a verifier le modele payant, mis en sommeil et non
 * supprime. Ici, c'est la production telle qu'elle est : une entreprise
 * publie, lit ses candidatures et consulte la CVtheque sans jamais voir un
 * prix — et rien ne peut lui en faire payer un par un chemin oublie.
 */
class ModeGratuitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.jeuncy.gratuit', true);
    }

    // Les fixtures portent desormais un code postal et un statut VERIFIED :
    // le modele match exige le premier a la publication et le second pour
    // approcher un candidat (MOBILE.md §4.0 et §6). Aucune assertion de ce
    // fichier ne change — ce qu'il verifie, c'est que la gratuite ne
    // rencontre aucun prix, pas la façon dont la fiche est remplie.
    private function company(string $email = 'rh@nexatech.example.com'): User
    {
        $user = User::create(['email' => $email, 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
        $company = $this->app->make(CompanyService::class)->createForUser($user, [
            'name' => 'NexaTech',
            'city' => 'Perpignan',
            'postal_code' => '66000',
        ]);
        $this->verifier($company);

        return $user->fresh();
    }

    private function cfa(): User
    {
        $user = User::create(['email' => 'contact@ida.example.com', 'password_hash' => 'x', 'role' => UserRole::CFA]);
        $cfa = $this->app->make(CfaOrganizationService::class)->createForUser($user, [
            'name' => 'IDA',
            'city' => 'Perpignan',
            'postal_code' => '66000',
        ]);
        $this->verifier($cfa);

        return $user->fresh();
    }

    // verification_status n'est pas fillable : pose par affectation directe,
    // comme le ferait CompanyVerificationService.
    private function verifier(Model $organization): void
    {
        $organization->verification_status = VerificationStatus::VERIFIED;
        $organization->verified_at = now();
        $organization->saveQuietly();
    }

    private function draftOfferFor(User $owner): JobOffer
    {
        return $this->app->make(JobOfferService::class)->createForUser($owner, [
            'title' => 'Developpeur web en alternance',
            'description' => 'Rejoins notre equipe.',
            'contract_type' => ContractType::ALTERNANCE->value,
            'city' => 'Perpignan',
            'postal_code' => '66000',
        ]);
    }

    // ------------------------------------------------------------------
    // Publier ne coute rien
    // ------------------------------------------------------------------

    public function test_a_company_publishes_a_draft_for_free(): void
    {
        $company = $this->company();
        $offer = $this->draftOfferFor($company);

        $this->actingAs($company, 'api')
            ->postJson("/api/job-offers/{$offer->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', JobOfferStatus::PUBLISHED->value)
            ->assertJsonPath('data.payment_status', PaymentStatus::FREE->value);

        $offer->refresh();
        $this->assertNull($offer->expires_at, 'Une offre gratuite n\'a pas d\'echeance.');
        $this->assertNotNull($offer->published_at);
        $this->assertNotNull($offer->applications_unlocked_at, 'Les candidatures sont incluses.');
    }

    public function test_a_cfa_publishes_for_free_too(): void
    {
        $cfa = $this->cfa();
        $offer = $this->draftOfferFor($cfa);

        $this->actingAs($cfa, 'api')
            ->postJson("/api/job-offers/{$offer->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.payment_status', PaymentStatus::FREE->value);
    }

    public function test_matching_candidates_are_notified_by_a_free_publication(): void
    {
        $candidate = User::create(['email' => 'lea@example.com', 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);
        CandidateProfile::create(['user_id' => $candidate->id, 'first_name' => 'Lea', 'last_name' => 'Girard', 'city' => 'Perpignan', 'birth_date' => '2004-05-01']);

        $company = $this->company();
        $offer = $this->draftOfferFor($company);

        $this->actingAs($company, 'api')->postJson("/api/job-offers/{$offer->id}/publish")->assertOk();

        $this->assertSame(1, $candidate->notifications()->count(), 'La notification « une offre te correspond » part comme pour une offre payee.');
    }

    public function test_an_expired_offer_goes_back_online_for_free(): void
    {
        $company = $this->company();
        $offer = $this->draftOfferFor($company);
        $offer->update(['status' => JobOfferStatus::EXPIRED, 'payment_status' => PaymentStatus::SUCCEEDED, 'expires_at' => now()->subDay()]);

        $this->actingAs($company, 'api')
            ->postJson("/api/job-offers/{$offer->id}/publish")
            ->assertOk();

        $offer->refresh();
        $this->assertSame(JobOfferStatus::PUBLISHED, $offer->status);
        $this->assertNull($offer->expires_at, 'La vieille echeance ne doit pas survivre, ExpireJobOffers la retirerait des la nuit suivante.');
    }

    public function test_a_manually_archived_offer_still_cannot_be_republished(): void
    {
        $company = $this->company();
        $offer = $this->draftOfferFor($company);
        $offer->update(['status' => JobOfferStatus::ARCHIVED, 'payment_status' => PaymentStatus::SUCCEEDED]);

        $this->actingAs($company, 'api')
            ->postJson("/api/job-offers/{$offer->id}/publish")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'JOB_OFFER_NOT_PAYABLE');
    }

    public function test_you_cannot_publish_someone_elses_offer_for_free(): void
    {
        $offer = $this->draftOfferFor($this->company());
        $other = $this->company('rh@autre.example.com');

        $this->actingAs($other, 'api')
            ->postJson("/api/job-offers/{$offer->id}/publish")
            ->assertStatus(403);

        $this->assertSame(JobOfferStatus::DRAFT, $offer->fresh()->status);
    }

    // Le chemin gratuit n'est pas une porte derobee : gratuite eteinte, il
    // se ferme aussi.
    public function test_free_publication_is_refused_when_the_free_mode_is_off(): void
    {
        Config::set('services.jeuncy.gratuit', false);
        $company = $this->company();
        $offer = $this->draftOfferFor($company);

        $this->actingAs($company, 'api')
            ->postJson("/api/job-offers/{$offer->id}/publish")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'FREE_PUBLICATION_DISABLED');
    }

    // ------------------------------------------------------------------
    // Tout ce qui etait derriere l'abonnement est ouvert
    // ------------------------------------------------------------------

    public function test_a_company_without_subscription_reads_its_applications(): void
    {
        $company = $this->company();
        $offer = $this->draftOfferFor($company);
        // Publiee sans passer par le chemin gratuit : l'acces doit venir de
        // hasPaidAccess(), pas seulement de applications_unlocked_at.
        $offer->update(['status' => JobOfferStatus::PUBLISHED, 'payment_status' => PaymentStatus::SUCCEEDED, 'published_at' => now()]);

        $this->actingAs($company, 'api')
            ->getJson("/api/job-offers/{$offer->id}/applications")
            ->assertOk();
    }

    public function test_a_company_without_subscription_opens_the_cvtheque(): void
    {
        $company = $this->company();

        $this->actingAs($company, 'api')->getJson('/api/cvtheque')->assertOk();
        $this->actingAs($company, 'api')->getJson('/api/cvtheque/access')->assertOk();
    }

    public function test_a_candidate_still_has_no_access_to_the_cvtheque(): void
    {
        $candidate = User::create(['email' => 'lea@example.com', 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);

        $this->actingAs($candidate, 'api')->getJson('/api/cvtheque')->assertStatus(403);
    }

    public function test_has_paid_access_reflects_the_free_mode_without_inventing_a_subscription(): void
    {
        $company = $this->company();
        $subscriptions = $this->app->make(SubscriptionService::class);

        $this->assertTrue($subscriptions->hasPaidAccess($company));
        $this->assertFalse($subscriptions->hasActiveSubscription($company), 'Aucun abonnement fictif ne doit apparaitre dans les stats.');
    }

    // ------------------------------------------------------------------
    // Rien ne peut etre facture
    // ------------------------------------------------------------------

    public function test_offer_checkout_is_closed(): void
    {
        $company = $this->company();
        $offer = $this->draftOfferFor($company);

        $this->actingAs($company, 'api')
            ->postJson("/api/job-offers/{$offer->id}/checkout")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PAYMENTS_DISABLED');
    }

    public function test_subscription_checkout_is_closed(): void
    {
        $this->actingAs($this->company(), 'api')
            ->postJson('/api/subscriptions/checkout')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PAYMENTS_DISABLED');
    }

    public function test_the_founder_offer_is_announced_unavailable(): void
    {
        $this->getJson('/api/subscriptions/founder-offer')
            ->assertOk()
            ->assertJsonPath('data.available', false);
    }

    // ------------------------------------------------------------------
    // Les taches de nuit ne retirent plus rien
    // ------------------------------------------------------------------

    public function test_an_ended_trial_becomes_a_free_publication_instead_of_being_archived(): void
    {
        $mail = Mockery::mock(MailService::class);
        $mail->shouldNotReceive('sendTrialEndedEmail');
        $this->app->instance(MailService::class, $mail);

        $cfa = $this->cfa();
        $cfa->cfaOrganization->update(['trial_started_at' => now()->subDays(20), 'trial_offers_count' => 1]);
        $offer = $this->draftOfferFor($cfa);
        $offer->update(['status' => JobOfferStatus::PUBLISHED, 'payment_status' => PaymentStatus::TRIAL, 'published_at' => now()->subDays(20)]);

        $this->artisan('job-offers:archive-expired-trials')->assertSuccessful();

        $offer->refresh();
        $this->assertSame(JobOfferStatus::PUBLISHED, $offer->status, 'L\'offre de l\'ecole partenaire ne doit pas disparaitre.');
        $this->assertSame(PaymentStatus::FREE, $offer->payment_status);
        $this->assertSame(0, $cfa->notifications()->count(), 'Pas de message « ton essai est termine » sur un service gratuit.');
    }

    public function test_an_archived_trial_offer_keeps_its_trial_status(): void
    {
        $cfa = $this->cfa();
        $offer = $this->draftOfferFor($cfa);
        $offer->update(['status' => JobOfferStatus::ARCHIVED, 'payment_status' => PaymentStatus::TRIAL]);

        $this->artisan('job-offers:archive-expired-trials')->assertSuccessful();

        // TRIAL sur une offre archivee est ce qui la rend republiable.
        $this->assertSame(PaymentStatus::TRIAL, $offer->fresh()->payment_status);
    }

    public function test_the_expiry_command_never_touches_a_free_offer(): void
    {
        $company = $this->company();
        $offer = $this->draftOfferFor($company);
        // Date d'echeance depassee laissee a dessein : la ceinture du
        // filtre sur payment_status doit suffire.
        $offer->update(['status' => JobOfferStatus::PUBLISHED, 'payment_status' => PaymentStatus::FREE, 'published_at' => now()->subMonths(2), 'expires_at' => now()->subDay()]);

        $this->artisan('job-offers:expire')->assertSuccessful();

        $this->assertSame(JobOfferStatus::PUBLISHED, $offer->fresh()->status);
    }
}
