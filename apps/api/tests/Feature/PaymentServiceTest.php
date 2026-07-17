<?php

namespace Tests\Feature;

use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;
use App\Services\CompanyService;
use App\Services\JobOfferService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;

    private JobOfferService $jobOfferService;

    protected function setUp(): void
    {
        parent::setUp();

        // Seule markPaymentSucceeded est testee ici (la logique declenchee par le
        // webhook, isolee de la verification de signature et du client Stripe
        // lui-meme, construit a la demande — voir PaymentService::stripe()) : aucune
        // dependance reelle a Stripe n'est necessaire pour ces tests.
        $this->service = $this->app->make(PaymentService::class);
        $this->jobOfferService = $this->app->make(JobOfferService::class);
    }

    private function makeOfferAwaitingPayment(): array
    {
        $user = User::create(['email' => 'rh@nexatech.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
        $this->app->make(CompanyService::class)->createForUser($user, ['name' => 'NexaTech']);
        $offer = $this->jobOfferService->createForUser($user->fresh(), [
            'title' => 'Développeur web full-stack en alternance',
            'description' => 'Rejoins notre équipe.',
            'contract_type' => ContractType::ALTERNANCE->value,
        ]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'job_offer_id' => $offer->id,
            'amount_cents' => 4900,
            'currency' => 'EUR',
            'status' => PaymentStatus::PENDING,
            'stripe_session_id' => 'cs_test_demo123',
        ]);

        return [$user->fresh(), $offer->fresh(), $payment];
    }

    public function test_mark_payment_succeeded_publishes_offer_and_notifies_user(): void
    {
        [$user, $offer, $payment] = $this->makeOfferAwaitingPayment();

        $this->service->markPaymentSucceeded('cs_test_demo123', 'pi_test_demo123');

        $this->assertSame(PaymentStatus::SUCCEEDED, $payment->fresh()->status);
        $this->assertSame('pi_test_demo123', $payment->fresh()->stripe_payment_intent_id);
        $this->assertSame(JobOfferStatus::PUBLISHED, $offer->fresh()->status);
        $this->assertSame(PaymentStatus::SUCCEEDED, $offer->fresh()->payment_status);
        $this->assertNotNull($offer->fresh()->published_at);
        $this->assertSame(1, $user->notifications()->where('type', NotificationType::PAYMENT_SUCCEEDED)->count());
    }

    public function test_mark_payment_succeeded_is_idempotent(): void
    {
        [, , $payment] = $this->makeOfferAwaitingPayment();
        $user = $payment->user;

        $this->service->markPaymentSucceeded('cs_test_demo123', 'pi_test_demo123');
        $this->service->markPaymentSucceeded('cs_test_demo123', 'pi_test_demo123');

        $this->assertSame(1, $user->notifications()->where('type', NotificationType::PAYMENT_SUCCEEDED)->count());
    }

    public function test_mark_payment_succeeded_does_nothing_for_unknown_session(): void
    {
        $this->service->markPaymentSucceeded('cs_test_unknown', 'pi_test_unknown');

        $this->assertSame(0, Payment::count());
    }
}
