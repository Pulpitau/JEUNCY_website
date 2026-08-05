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
        $this->assertSame(7900, $subscription->amount_cents);
        $this->assertSame(1, $user->notifications()->where('type', NotificationType::PAYMENT_SUCCEEDED)->count());
    }

    public function test_handle_checkout_completed_uses_cfa_price_for_cfa_user(): void
    {
        $user = User::create(['email' => 'contact@cfa-sup-alternance.example.com', 'password_hash' => 'x', 'role' => UserRole::CFA]);

        $this->service->handleCheckoutCompleted($this->fakeCheckoutSession($user, 'sub_test_cfa'));

        $subscription = Subscription::where('stripe_subscription_id', 'sub_test_cfa')->first();
        $this->assertSame(9900, $subscription->amount_cents);
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
}
