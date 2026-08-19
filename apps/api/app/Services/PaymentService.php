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

    // Isole le seul appel reseau du remboursement, pour que les tests puissent
    // eprouver toute la logique de refund() — garde-fous, depublication de
    // l'offre, notification, et non-ecriture en cas d'echec — sans jamais
    // emettre de mouvement d'argent reel.
    protected function performStripeRefund(string $paymentIntentId): void
    {
        $this->stripe()->refunds->create(['payment_intent' => $paymentIntentId]);
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

    // Le deblocage des candidatures a l'offre (50€ ponctuels) a ete SUPPRIME
    // le 2026-08-17 : l'acces aux candidatures et a la CVtheque passe desormais
    // exclusivement par l'abonnement mensuel. Le paiement a l'offre ne couvre
    // plus que la publication.
    //
    // markApplicationsUnlocked et la lecture de applications_unlocked_at sont
    // volontairement CONSERVES : des offres ont ete debloquees sous l'ancien
    // modele, et les entreprises concernees doivent garder l'acces qu'elles ont
    // paye. Seul le point d'entree qui permettait d'en acheter de nouveaux a
    // disparu.

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

    // Remboursement declenche depuis le back-office admin.
    //
    // Trois garde-fous, parce que c'est un mouvement d'argent reel et
    // irreversible cote Stripe :
    //
    // 1. Seul un paiement SUCCEEDED est remboursable. Un PENDING n'a rien
    //    encaisse, un FAILED non plus, et un REFUNDED le serait deux fois —
    //    Stripe refuserait, mais autant ne pas l'appeler pour rien.
    // 2. Le payment_intent est exige. C'est lui que Stripe rembourse ; une
    //    session sans intent signale un paiement jamais reellement capture,
    //    et l'appel echouerait avec un message obscur.
    // 3. La base n'est ecrite QU'APRES l'accord de Stripe. Marquer REFUNDED
    //    avant l'appel laisserait, en cas d'echec reseau, un paiement affiche
    //    comme rembourse alors que l'argent n'a pas bouge — l'ecart le plus
    //    couteux a rattraper ensuite.
    //
    // L'offre associee est depubliee : rembourser en laissant l'annonce en
    // ligne reviendrait a offrir le service. Elle repasse en ARCHIVED plutot
    // qu'en DRAFT pour rester distincte d'une offre jamais publiee, et
    // payment_status revient a PENDING pour qu'elle soit de nouveau payable.
    public function refund(Payment $payment): Payment
    {
        if ($payment->status !== PaymentStatus::SUCCEEDED) {
            throw new ApiException(
                'PAYMENT_NOT_REFUNDABLE',
                'Seul un paiement encaissé peut être remboursé.',
                409,
            );
        }

        if (! $payment->stripe_payment_intent_id) {
            throw new ApiException(
                'PAYMENT_INTENT_MISSING',
                "Ce paiement n'a pas de référence Stripe exploitable pour un remboursement.",
                409,
            );
        }

        $this->performStripeRefund($payment->stripe_payment_intent_id);

        $payment->update(['status' => PaymentStatus::REFUNDED]);

        $jobOffer = $payment->jobOffer;
        if ($jobOffer) {
            $jobOffer->update([
                'status' => JobOfferStatus::ARCHIVED,
                'payment_status' => PaymentStatus::PENDING,
            ]);
        }

        $payment->user->notifications()->create([
            'type' => NotificationType::PAYMENT_REFUNDED,
            'message' => $jobOffer
                ? "Ton paiement a été remboursé. L'offre \"{$jobOffer->title}\" a été retirée de la publication."
                : 'Ton paiement a été remboursé.',
            'link' => '/mes-paiements',
        ]);

        return $payment->fresh();
    }

    // Meme logique d'idempotence que markPaymentSucceeded, mais met a jour
    // applications_unlocked_at sur l'offre au lieu de la publier (elle l'est
    // deja dans ce parcours).
    //
    // Conservee alors que le deblocage a l'offre n'est plus vendable depuis le
    // 2026-08-17 : une session Stripe creee juste avant la bascule peut encore
    // arriver ici par un rejeu de webhook. La supprimer ferait tomber ce
    // paiement dans markPaymentSucceeded, qui republierait l'offre au lieu de
    // debloquer ses candidatures.
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
