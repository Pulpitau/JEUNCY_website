<?php

namespace App\Services;

use App\Enums\JobOfferStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
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
    public function __construct(
        private readonly JobOfferService $jobOfferService,
        private readonly SubscriptionService $subscriptionService,
    ) {}

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
        $jobOffer = $this->jobOfferService->requirePayableOffer($user, $jobOffer);
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $priceCents = $this->jobOfferService->priceCentsFor($jobOffer);

        $session = $this->stripe()->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'customer_email' => $user->email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $priceCents,
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
                'type' => 'offer_publication',
            ],
        ]);

        Payment::create([
            'user_id' => $user->id,
            'job_offer_id' => $jobOffer->id,
            'type' => PaymentType::OFFER_PUBLICATION,
            'amount_cents' => $priceCents,
            'currency' => 'EUR',
            'status' => PaymentStatus::PENDING,
            'stripe_session_id' => $session->id,
        ]);

        return $session->url;
    }

    // Paiement ponctuel distinct de la publication : debloque uniquement la
    // vue des candidatures de cette offre precise (voir
    // ApplicationService::listForOffer pour la garde qui lit
    // applications_unlocked_at). Alternative a l'abonnement mensuel pour une
    // entreprise/CFA qui ne publie qu'occasionnellement.
    public function createApplicationsUnlockCheckoutSession(User $user, JobOffer $jobOffer): string
    {
        $jobOffer = $this->jobOfferService->requireOwnedOffer($user, $jobOffer);

        if ($jobOffer->status !== JobOfferStatus::PUBLISHED) {
            throw new ApiException('JOB_OFFER_NOT_PUBLISHED', "Seule une offre publiée peut débloquer ses candidatures.", 409);
        }
        if ($jobOffer->applications_unlocked_at !== null) {
            throw new ApiException('APPLICATIONS_ALREADY_UNLOCKED', 'Les candidatures de cette offre sont déjà accessibles.', 409);
        }

        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $priceCents = config('services.stripe.applications_unlock_price_cents');

        $session = $this->stripe()->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'customer_email' => $user->email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $priceCents,
                    'product_data' => [
                        'name' => "Accès aux candidatures \u{2014} {$jobOffer->title}",
                    ],
                ],
            ]],
            'success_url' => $frontendUrl.'/mes-offres?checkout=applications_success',
            'cancel_url' => $frontendUrl.'/mes-offres?checkout=applications_cancelled',
            'metadata' => [
                'job_offer_id' => (string) $jobOffer->id,
                'user_id' => (string) $user->id,
                'type' => 'applications_unlock',
            ],
        ]);

        Payment::create([
            'user_id' => $user->id,
            'job_offer_id' => $jobOffer->id,
            'type' => PaymentType::APPLICATIONS_UNLOCK,
            'amount_cents' => $priceCents,
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

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
            'customer.subscription.updated' => $this->subscriptionService->handleSubscriptionUpdated($event->data->object),
            'customer.subscription.deleted' => $this->subscriptionService->handleSubscriptionDeleted($event->data->object),
            default => null,
        };
    }

    // Une session Stripe Checkout completee peut correspondre a trois choses
    // distinctes desormais : un abonnement mensuel, un deblocage de
    // candidatures a l'offre, ou (comportement d'origine) le paiement de
    // publication d'une offre — distingues par mode/metadata, jamais par le
    // montant (fragile, amene a changer).
    private function handleCheckoutCompleted(object $session): void
    {
        if (($session->mode ?? null) === 'subscription') {
            $this->subscriptionService->handleCheckoutCompleted($session);

            return;
        }

        if (($session->metadata->type ?? null) === 'applications_unlock') {
            $this->markApplicationsUnlocked($session->id);

            return;
        }

        $this->markPaymentSucceeded($session->id, $session->payment_intent);
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

    // Meme logique d'idempotence que markPaymentSucceeded, mais met a jour
    // applications_unlocked_at sur l'offre au lieu de la publier (deja
    // publiee dans ce parcours, voir createApplicationsUnlockCheckoutSession).
    public function markApplicationsUnlocked(string $stripeSessionId): void
    {
        $payment = Payment::where('stripe_session_id', $stripeSessionId)->first();

        if (! $payment || $payment->status === PaymentStatus::SUCCEEDED) {
            return;
        }

        $payment->update(['status' => PaymentStatus::SUCCEEDED]);

        $jobOffer = $payment->jobOffer;
        if (! $jobOffer) {
            return;
        }

        $jobOffer->update(['applications_unlocked_at' => now()]);

        $payment->user->notifications()->create([
            'type' => NotificationType::PAYMENT_SUCCEEDED,
            'message' => "Ton paiement a été validé, les candidatures de \"{$jobOffer->title}\" sont maintenant accessibles.",
            'link' => '/mes-offres',
        ]);
    }
}
