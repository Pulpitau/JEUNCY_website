<?php

namespace App\Services;

use App\Enums\JobOfferStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\JobOffer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class PaymentService
{
    public function __construct(private readonly JobOfferService $jobOfferService) {}

    // Construit le client Stripe a la demande plutot qu'au constructeur : seule
    // createCheckoutSessionForOffer en a besoin, pas le traitement du webhook
    // (Webhook::constructEvent est un appel statique). Sans cela, une cle Stripe
    // absente ferait planter le controleur entier, webhook Stripe compris.
    private function stripe(): StripeClient
    {
        return new StripeClient(config('services.stripe.secret'));
    }

    public function createCheckoutSessionForOffer(User $user, JobOffer $jobOffer): string
    {
        $jobOffer = $this->jobOfferService->requireOwnedDraftOffer($user, $jobOffer);
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        $session = $this->stripe()->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'customer_email' => $user->email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => config('services.stripe.job_offer_price_cents'),
                    'product_data' => [
                        'name' => "Publication de l'offre \u{2014} {$jobOffer->title}",
                    ],
                ],
            ]],
            'success_url' => $frontendUrl.'/mes-offres?checkout=success',
            'cancel_url' => $frontendUrl.'/mes-offres?checkout=cancelled',
            'metadata' => [
                'job_offer_id' => (string) $jobOffer->id,
                'user_id' => (string) $user->id,
            ],
        ]);

        Payment::create([
            'user_id' => $user->id,
            'job_offer_id' => $jobOffer->id,
            'amount_cents' => config('services.stripe.job_offer_price_cents'),
            'currency' => 'EUR',
            'status' => PaymentStatus::PENDING,
            'stripe_session_id' => $session->id,
        ]);

        return $session->url;
    }

    // Historique des paiements/factures de l'entreprise ou du CFA connecte (voir
    // "connu et a traiter plus tard" phase 3 dans CLAUDE.md).
    public function listOwn(User $user): Collection
    {
        return Payment::where('user_id', $user->id)->with('jobOffer')->latest()->get();
    }

    public function handleWebhook(string $payload, string $signature): void
    {
        try {
            $event = Webhook::constructEvent($payload, $signature, config('services.stripe.webhook_secret'));
        } catch (UnexpectedValueException|SignatureVerificationException) {
            throw new ApiException('INVALID_WEBHOOK_SIGNATURE', 'Signature Stripe invalide.', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $this->markPaymentSucceeded($session->id, $session->payment_intent);
        }
    }

    // Isole de la verification de signature Stripe pour rester testable sans
    // dependance reseau (voir PaymentServiceTest) : c'est cette methode qui
    // porte toute la logique metier declenchee par le webhook.
    public function markPaymentSucceeded(string $stripeSessionId, ?string $stripePaymentIntentId): void
    {
        $payment = Payment::where('stripe_session_id', $stripeSessionId)->first();

        // Idempotence : evenement Stripe deja traite (retry) ou session inconnue.
        if (! $payment || $payment->status === PaymentStatus::SUCCEEDED) {
            return;
        }

        $payment->update([
            'status' => PaymentStatus::SUCCEEDED,
            'stripe_payment_intent_id' => $stripePaymentIntentId,
        ]);

        $jobOffer = $payment->jobOffer;
        if (! $jobOffer) {
            return;
        }

        $jobOffer->update([
            'status' => JobOfferStatus::PUBLISHED,
            'payment_status' => PaymentStatus::SUCCEEDED,
            'published_at' => now(),
        ]);

        $payment->user->notifications()->create([
            'type' => NotificationType::PAYMENT_SUCCEEDED,
            'message' => "Ton paiement a été validé, l'offre \"{$jobOffer->title}\" est maintenant publiée.",
            'link' => '/mes-offres',
        ]);
    }
}
