<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use stdClass;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Comme PaymentServiceTest : seule la logique declenchee par les
        // webhooks Stripe est testee ici (aucun appel reseau reel), pas
        // createCheckoutSession/cancel qui appellent le client Stripe.
        $this->service = $this->app->make(SubscriptionService::class);
    }

    private function makeCompanyUser(): User
    {
        return User::create(['email' => 'rh@nexatech.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
    }

    private function fakeCheckoutSession(User $user, string $subscriptionId = 'sub_test_123'): object
    {
        $session = new stdClass;
        $session->metadata = new stdClass;
        $session->metadata->user_id = (string) $user->id;
        $session->subscription = $subscriptionId;
        $session->customer = 'cus_test_123';

        return $session;
    }

    public function test_has_active_subscription_is_false_by_default(): void
    {
        $user = $this->makeCompanyUser();

        $this->assertFalse($this->service->hasActiveSubscription($user));
    }

    public function test_handle_checkout_completed_creates_active_subscription_and_notifies(): void
    {
        $user = $this->makeCompanyUser();

        $this->service->handleCheckoutCompleted($this->fakeCheckoutSession($user));

        $this->assertTrue($this->service->hasActiveSubscription($user->fresh()));
        $subscription = Subscription::where('stripe_subscription_id', 'sub_test_123')->first();
        $this->assertNotNull($subscription);
        $this->assertSame(SubscriptionStatus::ACTIVE, $subscription->status);
        // Session sans metadonnee de montant : repli sur le tarif plein (cas
        // des sessions creees avant l'ajout du tarif fondateur).
        $this->assertSame(49900, $subscription->amount_cents);
        $this->assertFalse($subscription->is_founder_rate);
        $this->assertSame(1, $user->notifications()->where('type', NotificationType::PAYMENT_SUCCEEDED)->count());
    }

    public function test_handle_checkout_completed_uses_cfa_price_for_cfa_user(): void
    {
        $user = User::create(['email' => 'contact@cfa-sup-alternance.example.com', 'password_hash' => 'x', 'role' => UserRole::CFA]);

        $this->service->handleCheckoutCompleted($this->fakeCheckoutSession($user, 'sub_test_cfa'));

        $subscription = Subscription::where('stripe_subscription_id', 'sub_test_cfa')->first();
        $this->assertSame(49900, $subscription->amount_cents);
    }

    // --- Tarif d'ouverture (299€, 50 premiers, verrouille a vie) ---

    public function test_founder_rate_is_offered_while_seats_remain(): void
    {
        $this->assertTrue($this->service->founderRateAvailable());
        $this->assertSame(50, $this->service->founderSeatsRemaining());
        $this->assertSame(29900, $this->service->priceCentsFor($this->makeCompanyUser()));
    }

    public function test_founder_rate_is_persisted_from_session_metadata(): void
    {
        $user = $this->makeCompanyUser();
        $session = $this->fakeCheckoutSession($user, 'sub_founder_1');
        $session->metadata->founder_rate = '1';
        $session->metadata->amount_cents = '29900';

        $this->service->handleCheckoutCompleted($session);

        $subscription = Subscription::where('stripe_subscription_id', 'sub_founder_1')->first();
        $this->assertTrue($subscription->is_founder_rate);
        $this->assertSame(29900, $subscription->amount_cents);
        $this->assertSame(49, $this->service->founderSeatsRemaining());
    }

    public function test_founder_rate_closes_once_all_seats_are_taken(): void
    {
        config(['services.stripe.founder_seats_total' => 2]);
        $user = $this->makeCompanyUser();

        foreach (['sub_f1', 'sub_f2'] as $id) {
            Subscription::create([
                'user_id' => $user->id,
                'status' => SubscriptionStatus::ACTIVE,
                'amount_cents' => 29900,
                'is_founder_rate' => true,
                'stripe_subscription_id' => $id,
                'stripe_customer_id' => 'cus_'.$id,
            ]);
        }

        $this->assertFalse($this->service->founderRateAvailable());
        $this->assertSame(0, $this->service->founderSeatsRemaining());
        // Le 51e paie le tarif plein.
        $this->assertSame(49900, $this->service->priceCentsFor($user));
    }

    // Une place fondateur consommee ne revient JAMAIS dans le pot, meme apres
    // resiliation : sinon le compteur public remonterait et rouvrirait le tarif
    // a des retardataires, alors qu'il est annonce comme reserve aux 50
    // premiers.
    public function test_canceled_founder_subscription_does_not_free_its_seat(): void
    {
        config(['services.stripe.founder_seats_total' => 1]);
        $user = $this->makeCompanyUser();

        Subscription::create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::CANCELED,
            'amount_cents' => 29900,
            'is_founder_rate' => true,
            'stripe_subscription_id' => 'sub_f_canceled',
            'stripe_customer_id' => 'cus_f_canceled',
        ]);

        $this->assertSame(0, $this->service->founderSeatsRemaining());
        $this->assertFalse($this->service->founderRateAvailable());
    }

    public function test_founder_seats_remaining_never_goes_negative(): void
    {
        config(['services.stripe.founder_seats_total' => 1]);
        $user = $this->makeCompanyUser();

        foreach (['sub_n1', 'sub_n2', 'sub_n3'] as $id) {
            Subscription::create([
                'user_id' => $user->id,
                'status' => SubscriptionStatus::ACTIVE,
                'amount_cents' => 29900,
                'is_founder_rate' => true,
                'stripe_subscription_id' => $id,
                'stripe_customer_id' => 'cus_'.$id,
            ]);
        }

        $this->assertSame(0, $this->service->founderSeatsRemaining());
    }

    public function test_handle_subscription_updated_marks_past_due(): void
    {
        $user = $this->makeCompanyUser();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::ACTIVE,
            'amount_cents' => 7900,
            'stripe_subscription_id' => 'sub_test_updated',
            'stripe_customer_id' => 'cus_test_updated',
        ]);

        $stripeSubscription = new stdClass;
        $stripeSubscription->id = 'sub_test_updated';
        $stripeSubscription->status = 'past_due';

        $this->service->handleSubscriptionUpdated($stripeSubscription);

        $this->assertSame(SubscriptionStatus::PAST_DUE, $subscription->fresh()->status);
        $this->assertFalse($this->service->hasActiveSubscription($user->fresh()));
    }

    public function test_handle_subscription_deleted_marks_canceled(): void
    {
        $user = $this->makeCompanyUser();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::ACTIVE,
            'amount_cents' => 7900,
            'stripe_subscription_id' => 'sub_test_deleted',
            'stripe_customer_id' => 'cus_test_deleted',
        ]);

        $stripeSubscription = new stdClass;
        $stripeSubscription->id = 'sub_test_deleted';

        $this->service->handleSubscriptionDeleted($stripeSubscription);

        $this->assertSame(SubscriptionStatus::CANCELED, $subscription->fresh()->status);
        $this->assertFalse($this->service->hasActiveSubscription($user->fresh()));
    }

    public function test_handle_subscription_updated_ignores_unknown_subscription(): void
    {
        $stripeSubscription = new stdClass;
        $stripeSubscription->id = 'sub_unknown';
        $stripeSubscription->status = 'active';

        $this->service->handleSubscriptionUpdated($stripeSubscription);

        $this->assertSame(0, Subscription::count());
    }

    // Le point clef de la separation des deux methodes : un ADMIN a le DROIT
    // d'utiliser les fonctionnalites payantes, mais n'a PAS d'abonnement. Si
    // hasActiveSubscription se mettait a repondre true pour lui, le
    // back-office afficherait "abonnement actif" et le chiffre d'affaires
    // compterait un client fantome.
    public function test_admin_has_paid_access_but_no_actual_subscription(): void
    {
        $admin = User::create([
            'email' => 'admin@jeuncy.com',
            'password_hash' => 'x',
            'role' => UserRole::ADMIN,
        ]);

        $this->assertTrue($this->service->hasPaidAccess($admin));
        $this->assertFalse($this->service->hasActiveSubscription($admin));
        $this->assertNull($this->service->currentFor($admin));
        $this->assertSame(0, Subscription::count());
    }

    // Une entreprise sans abonnement ne beneficie evidemment pas de la
    // derogation admin : la garde reste fermee pour tout le monde d'autre.
    public function test_company_without_subscription_has_no_paid_access(): void
    {
        $this->assertFalse($this->service->hasPaidAccess($this->makeCompanyUser()));
    }
}
