<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Stripe\StripeClient;

class SubscriptionService
{
    // Construit le client Stripe a la demande (voir PaymentService::stripe pour
    // la meme raison) : les webhooks d'abonnement ne doivent pas exiger de cle
    // Stripe pour etre traites.
    private function stripe(): StripeClient
    {
        return new StripeClient(config('services.stripe.secret'));
    }

    // Tarif mensuel plein, potentiellement different entreprise/CFA (voir
    // config/services.php ; identiques depuis le 2026-08-17, mais les deux
    // grilles restent separees pour pouvoir diverger sans migration).
    public function standardPriceCentsFor(User $user): int
    {
        return $user->role === UserRole::CFA
            ? config('services.stripe.cfa_subscription_price_cents')
            : config('services.stripe.company_subscription_price_cents');
    }

    // Places restantes au tarif d'ouverture. Jamais negatif : si le total est
    // un jour abaisse en config alors que des places sont deja prises, on
    // affiche 0 plutot qu'un nombre negatif absurde cote marketing.
    public function founderSeatsRemaining(): int
    {
        $total = (int) config('services.stripe.founder_seats_total');

        return max(0, $total - $this->founderSeatsTaken());
    }

    // Volontairement SANS filtre sur le statut : un abonnement fondateur
    // resilie a bien consomme sa place. Sinon le compteur public remonterait a
    // chaque resiliation et rouvrirait le tarif a des retardataires, alors que
    // l'offre est annoncee comme reservee aux 50 premiers.
    public function founderSeatsTaken(): int
    {
        return Subscription::where('is_founder_rate', true)->count();
    }

    public function founderRateAvailable(): bool
    {
        return $this->founderSeatsRemaining() > 0;
    }

    // Tarif reellement facture : le tarif d'ouverture s'il reste des places au
    // moment de la souscription, sinon le tarif plein.
    public function priceCentsFor(User $user): int
    {
        return $this->founderRateAvailable()
            ? (int) config('services.stripe.founder_subscription_price_cents')
            : $this->standardPriceCentsFor($user);
    }

    // Etat de l'offre d'ouverture, expose publiquement (sans authentification)
    // pour alimenter le compteur de la page Tarifs.
    public function founderOffer(): array
    {
        return [
            'price_cents' => (int) config('services.stripe.founder_subscription_price_cents'),
            'standard_company_price_cents' => (int) config('services.stripe.company_subscription_price_cents'),
            'standard_cfa_price_cents' => (int) config('services.stripe.cfa_subscription_price_cents'),
            'seats_total' => (int) config('services.stripe.founder_seats_total'),
            'seats_taken' => $this->founderSeatsTaken(),
            'seats_remaining' => $this->founderSeatsRemaining(),
            'available' => $this->founderRateAvailable(),
        ];
    }

    public function createCheckoutSession(User $user): string
    {
        if ($this->hasActiveSubscription($user)) {
            throw new ApiException('SUBSCRIPTION_ALREADY_ACTIVE', 'Tu as déjà un abonnement actif.', 409);
        }

        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        // Le tarif est fige ICI, au moment de la souscription, et transporte
        // jusqu'au webhook via les metadonnees (voir handleCheckoutCompleted) :
        // c'est ce qui verrouille le tarif fondateur a vie. Le recalculer au
        // webhook donnerait un montant faux des que les 50 places sont prises,
        // alors que Stripe, lui, continuerait de prelever 299€.
        $isFounderRate = $this->founderRateAvailable();
        $priceCents = $isFounderRate
            ? (int) config('services.stripe.founder_subscription_price_cents')
            : $this->standardPriceCentsFor($user);

        $audience = $user->role === UserRole::CFA ? 'CFA' : 'Entreprise';
        $productName = $isFounderRate
            ? "Abonnement Jeuncy {$audience} — Tarif fondateur (tout illimité)"
            : "Abonnement Jeuncy {$audience} — tout illimité";

        $session = $this->stripe()->checkout->sessions->create([
            'mode' => 'subscription',
            'payment_method_types' => ['card'],
            'customer_email' => $user->email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $priceCents,
                    'recurring' => ['interval' => 'month'],
                    'product_data' => ['name' => $productName],
                ],
            ]],
            'success_url' => $frontendUrl.'/mes-offres?subscription=success',
            'cancel_url' => $frontendUrl.'/mes-offres?subscription=cancelled',
            'metadata' => [
                'user_id' => (string) $user->id,
                'type' => 'subscription',
                'founder_rate' => $isFounderRate ? '1' : '0',
                'amount_cents' => (string) $priceCents,
            ],
        ]);

        return $session->url;
    }

    public function hasActiveSubscription(User $user): bool
    {
        return Subscription::where('user_id', $user->id)
            ->where('status', SubscriptionStatus::ACTIVE)
            ->exists();
    }

    // La plus recente en premier : suffisant pour l'affichage (un utilisateur
    // ne peut pas avoir deux abonnements actifs a la fois, voir
    // createCheckoutSession qui bloque ce cas), et donne un historique
    // raisonnable meme apres resiliation.
    public function currentFor(User $user): ?Subscription
    {
        return Subscription::where('user_id', $user->id)->latest()->first();
    }

    // Resiliation a la fin de la periode deja payee (pas immediate) : pratique
    // standard, l'utilisateur garde l'acces jusqu'a la fin de ce qu'il a payé.
    // Le statut local reste ACTIVE jusqu'a ce que Stripe confirme la fin reelle
    // via customer.subscription.deleted (voir handleSubscriptionDeleted) —
    // canceled_at sert uniquement a afficher "resiliation prevue" en attendant.
    public function cancel(User $user): Subscription
    {
        $subscription = Subscription::where('user_id', $user->id)
            ->where('status', SubscriptionStatus::ACTIVE)
            ->first();

        if (! $subscription) {
            throw new ApiException('SUBSCRIPTION_NOT_FOUND', 'Aucun abonnement actif à annuler.', 404);
        }

        $this->stripe()->subscriptions->update($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => true,
        ]);

        $subscription->update(['canceled_at' => now()]);

        return $subscription;
    }

    // Declenche par PaymentService::handleWebhook sur checkout.session.completed
    // en mode 'subscription' (le mode 'payment', publication d'offre ou
    // deblocage de candidatures, reste gere par PaymentService lui-meme).
    public function handleCheckoutCompleted(object $session): void
    {
        $userId = (int) ($session->metadata->user_id ?? 0);
        $user = User::find($userId);
        if (! $user || ! $session->subscription) {
            return;
        }

        // Montant et tarif fondateur relus des metadonnees, PAS recalcules :
        // seul ce qui a ete decide a la souscription correspond a ce que Stripe
        // preleve reellement (voir createCheckoutSession). Repli sur le tarif
        // plein uniquement pour les sessions anterieures a cette metadonnee.
        $isFounderRate = ($session->metadata->founder_rate ?? '0') === '1';
        $amountCents = (int) ($session->metadata->amount_cents ?? 0)
            ?: $this->standardPriceCentsFor($user);

        Subscription::updateOrCreate(
            ['stripe_subscription_id' => $session->subscription],
            [
                'user_id' => $user->id,
                'status' => SubscriptionStatus::ACTIVE,
                'amount_cents' => $amountCents,
                'is_founder_rate' => $isFounderRate,
                'currency' => 'EUR',
                'stripe_customer_id' => $session->customer,
            ],
        );

        $user->notifications()->create([
            'type' => NotificationType::PAYMENT_SUCCEEDED,
            'message' => $isFounderRate
                ? 'Ton abonnement Jeuncy est activé au tarif fondateur, conservé tant que tu restes abonné : offres illimitées, candidatures et CVthèque inclus.'
                : 'Ton abonnement Jeuncy est activé : offres illimitées, candidatures et CVthèque inclus.',
            'link' => '/mes-offres',
        ]);
    }

    public function handleSubscriptionUpdated(object $stripeSubscription): void
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();
        if (! $subscription) {
            return;
        }

        $status = match ($stripeSubscription->status ?? null) {
            'active', 'trialing' => SubscriptionStatus::ACTIVE,
            'past_due', 'unpaid', 'incomplete' => SubscriptionStatus::PAST_DUE,
            'canceled' => SubscriptionStatus::CANCELED,
            default => $subscription->status,
        };

        // current_period_end a bouge de place cote API Stripe selon les
        // versions (top-level historiquement, deplace sous items.data[] plus
        // recemment) : on tente les deux, purement informatif (voir migration).
        $periodEnd = $stripeSubscription->current_period_end
            ?? ($stripeSubscription->items->data[0]->current_period_end ?? null);

        $subscription->update([
            'status' => $status,
            'current_period_end' => $periodEnd ? Carbon::createFromTimestamp($periodEnd) : $subscription->current_period_end,
        ]);
    }

    public function handleSubscriptionDeleted(object $stripeSubscription): void
    {
        Subscription::where('stripe_subscription_id', $stripeSubscription->id)
            ->update(['status' => SubscriptionStatus::CANCELED]);
    }
}
