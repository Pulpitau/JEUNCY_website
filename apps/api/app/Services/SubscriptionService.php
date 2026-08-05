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

    // Tarif mensuel, different entreprise/CFA (voir config/services.php) —
    // memes tarifs a l'offre que priceCentsFor cote JobOfferService, mais
    // volontairement une methode separee : les deux grilles tarifaires sont
    // amenees a evoluer independamment l'une de l'autre.
    public function priceCentsFor(User $user): int
    {
        return $user->role === UserRole::CFA
            ? config('services.stripe.cfa_subscription_price_cents')
            : config('services.stripe.company_subscription_price_cents');
    }

    public function createCheckoutSession(User $user): string
    {
        if ($this->hasActiveSubscription($user)) {
            throw new ApiException('SUBSCRIPTION_ALREADY_ACTIVE', 'Tu as déjà un abonnement actif.', 409);
        }

        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $priceCents = $this->priceCentsFor($user);
        $productName = $user->role === UserRole::CFA
            ? 'Abonnement Jeuncy CFA — publication illimitée'
            : 'Abonnement Jeuncy Entreprise — publication illimitée';

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

        Subscription::updateOrCreate(
            ['stripe_subscription_id' => $session->subscription],
            [
                'user_id' => $user->id,
                'status' => SubscriptionStatus::ACTIVE,
                'amount_cents' => $this->priceCentsFor($user),
                'currency' => 'EUR',
                'stripe_customer_id' => $session->customer,
            ],
        );

        $user->notifications()->create([
            'type' => NotificationType::PAYMENT_SUCCEEDED,
            'message' => 'Ton abonnement Jeuncy est activé : publication illimitée et accès aux candidatures inclus.',
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
