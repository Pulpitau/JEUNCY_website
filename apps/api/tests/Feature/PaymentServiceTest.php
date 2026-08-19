<?php

namespace Tests\Feature;

use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\Payment;
use App\Models\User;
use App\Services\CompanyService;
use App\Services\JobOfferService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
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

    public function test_list_own_returns_only_users_payments(): void
    {
        [$user] = $this->makeOfferAwaitingPayment();
        $other = User::create(['email' => 'contact@cafedeslices.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
        $this->app->make(CompanyService::class)->createForUser($other, ['name' => 'Café des Lices']);
        $otherOffer = $this->jobOfferService->createForUser($other->fresh(), [
            'title' => 'Serveur en saisonnier',
            'description' => 'Rejoins notre équipe.',
            'contract_type' => ContractType::SAISONNIER->value,
        ]);
        Payment::create([
            'user_id' => $other->id,
            'job_offer_id' => $otherOffer->id,
            'amount_cents' => 4900,
            'currency' => 'EUR',
            'status' => PaymentStatus::PENDING,
            'stripe_session_id' => 'cs_test_other',
        ]);

        $payments = $this->service->listOwn($user);

        $this->assertCount(1, $payments);
        $this->assertSame('cs_test_demo123', $payments->first()->stripe_session_id);
    }

    // Deblocage payant des candidatures d'une offre precise (nouveau modele
    // economique du 2026-08-05) : markApplicationsUnlocked ne doit ni publier
    // ni republier l'offre (deja publiee dans ce parcours), seulement poser
    // applications_unlocked_at — a la difference de markPaymentSucceeded.
    public function test_mark_applications_unlocked_sets_flag_without_touching_offer_status(): void
    {
        [$user, $offer] = $this->makeOfferAwaitingPayment();
        $offer->update(['status' => JobOfferStatus::PUBLISHED, 'published_at' => now()]);
        $payment = Payment::create([
            'user_id' => $user->id,
            'job_offer_id' => $offer->id,
            'type' => PaymentType::APPLICATIONS_UNLOCK,
            'amount_cents' => 5000,
            'currency' => 'EUR',
            'status' => PaymentStatus::PENDING,
            'stripe_session_id' => 'cs_test_unlock123',
        ]);

        $this->service->markApplicationsUnlocked('cs_test_unlock123');

        $this->assertSame(PaymentStatus::SUCCEEDED, $payment->fresh()->status);
        $this->assertNotNull($offer->fresh()->applications_unlocked_at);
        $this->assertSame(JobOfferStatus::PUBLISHED, $offer->fresh()->status);
        $this->assertSame(1, $user->notifications()->where('type', NotificationType::PAYMENT_SUCCEEDED)->count());
    }

    public function test_mark_applications_unlocked_is_idempotent(): void
    {
        [$user, $offer] = $this->makeOfferAwaitingPayment();
        Payment::create([
            'user_id' => $user->id,
            'job_offer_id' => $offer->id,
            'type' => PaymentType::APPLICATIONS_UNLOCK,
            'amount_cents' => 5000,
            'currency' => 'EUR',
            'status' => PaymentStatus::PENDING,
            'stripe_session_id' => 'cs_test_unlock456',
        ]);

        $this->service->markApplicationsUnlocked('cs_test_unlock456');
        $this->service->markApplicationsUnlocked('cs_test_unlock456');

        $this->assertSame(1, $user->notifications()->where('type', NotificationType::PAYMENT_SUCCEEDED)->count());
    }

    // Remboursements. Le vrai appel Stripe est substitue : ces tests ne
    // doivent jamais deplacer d'argent, meme lances par megarde avec une cle
    // valide dans l'environnement.
    private function serviceWithFakeStripe(?\Closure $onRefund = null): PaymentService
    {
        return new class($this->app->make(JobOfferService::class), $this->app->make(SubscriptionService::class), $onRefund) extends PaymentService
        {
            public array $refundedIntents = [];

            public function __construct($jobOfferService, $subscriptionService, private $onRefund)
            {
                parent::__construct($jobOfferService, $subscriptionService);
            }

            protected function performStripeRefund(string $paymentIntentId): void
            {
                $this->refundedIntents[] = $paymentIntentId;

                if ($this->onRefund) {
                    ($this->onRefund)($paymentIntentId);
                }
            }
        };
    }

    private function succeededPayment(): array
    {
        [$user, $offer, $payment] = $this->makeOfferAwaitingPayment();
        $this->service->markPaymentSucceeded('cs_test_demo123', 'pi_test_demo123');

        return [$user->fresh(), $offer->fresh(), $payment->fresh()];
    }

    public function test_refund_marks_payment_archives_offer_and_notifies(): void
    {
        [$user, $offer, $payment] = $this->succeededPayment();

        $refunded = $this->serviceWithFakeStripe()->refund($payment);

        $this->assertSame(PaymentStatus::REFUNDED, $refunded->status);
        // L'offre est retiree : rembourser en la laissant en ligne
        // reviendrait a offrir le service.
        $this->assertSame(JobOfferStatus::ARCHIVED, $offer->fresh()->status);
        // ... et redevient payable.
        $this->assertSame(PaymentStatus::PENDING, $offer->fresh()->payment_status);
        $this->assertSame(
            1,
            $user->notifications()->where('type', NotificationType::PAYMENT_REFUNDED)->count(),
        );
    }

    // Garde-fou principal : sans lui, un clic de trop rembourserait deux fois.
    public function test_refund_is_refused_on_an_already_refunded_payment(): void
    {
        [, , $payment] = $this->succeededPayment();
        $service = $this->serviceWithFakeStripe();
        $service->refund($payment);

        $this->expectException(ApiException::class);
        $service->refund($payment->fresh());
    }

    public function test_refund_is_refused_on_a_pending_payment(): void
    {
        [, , $payment] = $this->makeOfferAwaitingPayment();

        $this->expectException(ApiException::class);
        $this->serviceWithFakeStripe()->refund($payment);
    }

    // Sans payment_intent, Stripe n'a rien a rembourser : mieux vaut un refus
    // net qu'un appel qui echoue avec un message obscur.
    public function test_refund_is_refused_without_a_stripe_payment_intent(): void
    {
        [, , $payment] = $this->succeededPayment();
        $payment->update(['stripe_payment_intent_id' => null]);

        $this->expectException(ApiException::class);
        $this->serviceWithFakeStripe()->refund($payment->fresh());
    }

    // La base ne doit etre ecrite QU'APRES l'accord de Stripe : en cas
    // d'echec, un paiement affiche comme rembourse alors que l'argent n'a pas
    // bouge serait l'ecart le plus couteux a rattraper.
    public function test_payment_stays_succeeded_when_stripe_refuses(): void
    {
        [, $offer, $payment] = $this->succeededPayment();

        $service = $this->serviceWithFakeStripe(function () {
            throw new \RuntimeException('Stripe indisponible');
        });

        try {
            $service->refund($payment);
            $this->fail('Un echec Stripe devrait remonter.');
        } catch (\RuntimeException) {
            // attendu
        }

        $this->assertSame(PaymentStatus::SUCCEEDED, $payment->fresh()->status);
        $this->assertSame(JobOfferStatus::PUBLISHED, $offer->fresh()->status);
    }
}
