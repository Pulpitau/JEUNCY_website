<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AdminService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use stdClass;
use Tests\TestCase;

/**
 * Le chiffre d'affaires recurrent.
 *
 * Avant ces tests, un abonnement mensuel n'ecrivait rien dans `payments` : le
 * CA du back-office et la page "Mes paiements" du client ignoraient donc
 * totalement les 299/499 EUR preleves chaque mois, c'est-a-dire la principale
 * source de revenus. Ce qui suit couvre le chemin complet, de la facture
 * Stripe au chiffre affiche a l'admin.
 */
class SubscriptionRevenueTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Aucun appel reseau : seule la logique declenchee par les webhooks est
        // testee (comme SubscriptionServiceTest).
        $this->service = $this->app->make(SubscriptionService::class);
    }

    private function abonne(string $email = 'rh@nexatech.example.com', int $montant = 29900): array
    {
        $user = User::create(['email' => $email, 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::ACTIVE,
            'amount_cents' => $montant,
            'is_founder_rate' => $montant === 29900,
            'stripe_subscription_id' => 'sub_'.$user->id,
            'stripe_customer_id' => 'cus_'.$user->id,
        ]);

        return [$user, $subscription];
    }

    private function facture(string $id, string $subscriptionId, int $montant = 29900): object
    {
        $invoice = new stdClass;
        $invoice->id = $id;
        $invoice->subscription = $subscriptionId;
        $invoice->amount_paid = $montant;
        $invoice->currency = 'eur';
        $invoice->payment_intent = 'pi_'.$id;

        return $invoice;
    }

    public function test_a_paid_invoice_records_a_subscription_payment(): void
    {
        [$user, $subscription] = $this->abonne();

        $this->service->handleInvoicePaid($this->facture('in_001', $subscription->stripe_subscription_id));

        $paiement = Payment::firstOrFail();
        $this->assertSame($user->id, $paiement->user_id);
        $this->assertSame(PaymentType::SUBSCRIPTION, $paiement->type);
        $this->assertSame(PaymentStatus::SUCCEEDED, $paiement->status);
        $this->assertSame(29900, $paiement->amount_cents);
        $this->assertSame('EUR', $paiement->currency);
        // Un abonnement ne se rattache a aucune offre : il les couvre toutes.
        $this->assertNull($paiement->job_offer_id);
        $this->assertSame('in_001', $paiement->stripe_invoice_id);
    }

    // Le renouvellement du mois suivant est une SECONDE recette, pas une mise
    // a jour de la premiere. Sans cela le CA resterait bloque au premier mois.
    public function test_each_monthly_renewal_adds_its_own_revenue_line(): void
    {
        [, $subscription] = $this->abonne();

        $this->service->handleInvoicePaid($this->facture('in_mois_1', $subscription->stripe_subscription_id));
        $this->service->handleInvoicePaid($this->facture('in_mois_2', $subscription->stripe_subscription_id));
        $this->service->handleInvoicePaid($this->facture('in_mois_3', $subscription->stripe_subscription_id));

        $this->assertSame(3, Payment::count());
        $this->assertSame(89700, (int) Payment::sum('amount_cents'));
    }

    // Stripe rejoue ses webhooks. Un rejeu qui compterait deux fois le meme
    // prelevement gonflerait le CA sans qu'aucune erreur ne le signale — le
    // genre de faux chiffre sur lequel on prend ensuite des decisions.
    public function test_a_replayed_invoice_is_not_counted_twice(): void
    {
        [, $subscription] = $this->abonne();
        $facture = $this->facture('in_rejeu', $subscription->stripe_subscription_id);

        $this->service->handleInvoicePaid($facture);
        $this->service->handleInvoicePaid($facture);
        $this->service->handleInvoicePaid($facture);

        $this->assertSame(1, Payment::count());
    }

    // Le montant fait foi cote Stripe, pas cote Jeuncy : remise, prorata ou
    // changement de tarif, c'est l'encaissement reel qui doit etre comptabilise.
    public function test_the_invoice_amount_wins_over_the_local_subscription_amount(): void
    {
        [, $subscription] = $this->abonne(montant: 49900);

        $this->service->handleInvoicePaid(
            $this->facture('in_prorata', $subscription->stripe_subscription_id, montant: 12475),
        );

        $this->assertSame(12475, Payment::firstOrFail()->amount_cents);
        // L'abonnement lui-meme n'est pas reecrit par une facture.
        $this->assertSame(49900, $subscription->fresh()->amount_cents);
    }

    public function test_an_invoice_for_an_unknown_subscription_is_ignored(): void
    {
        $this->abonne();

        $this->service->handleInvoicePaid($this->facture('in_inconnue', 'sub_jamais_vue'));

        $this->assertSame(0, Payment::count());
    }

    public function test_an_invoice_without_any_subscription_is_ignored(): void
    {
        $this->abonne();

        $invoice = new stdClass;
        $invoice->id = 'in_hors_abo';
        $invoice->amount_paid = 5000;

        $this->service->handleInvoicePaid($invoice);

        $this->assertSame(0, Payment::count());
    }

    // Stripe a deplace l'abonnement sous `parent` dans ses versions recentes.
    // Sans ce repli, une montee de version de l'API ferait silencieusement
    // disparaitre tout le CA recurrent : les factures arriveraient encore, mais
    // aucune ne serait rattachee a un abonnement.
    public function test_the_subscription_is_found_in_the_new_stripe_invoice_shape(): void
    {
        [, $subscription] = $this->abonne();

        $details = new stdClass;
        $details->subscription = $subscription->stripe_subscription_id;
        $parent = new stdClass;
        $parent->subscription_details = $details;

        $invoice = new stdClass;
        $invoice->id = 'in_nouvelle_forme';
        $invoice->subscription = null;
        $invoice->parent = $parent;
        $invoice->amount_paid = 29900;
        $invoice->currency = 'eur';

        $this->service->handleInvoicePaid($invoice);

        $this->assertSame(1, Payment::count());
        $this->assertSame(PaymentType::SUBSCRIPTION, Payment::firstOrFail()->type);
    }

    // ------------------------------------------------------------------
    // Ce que l'admin lit
    // ------------------------------------------------------------------

    public function test_admin_revenue_now_includes_subscriptions_and_splits_them(): void
    {
        [$user, $subscription] = $this->abonne();
        // Une publication a l'unite, pour verifier la ventilation.
        Payment::create([
            'user_id' => $user->id,
            'type' => PaymentType::OFFER_PUBLICATION,
            'amount_cents' => 999,
            'currency' => 'EUR',
            'status' => PaymentStatus::SUCCEEDED,
            'stripe_session_id' => 'cs_ponctuel',
        ]);

        $this->service->handleInvoicePaid($this->facture('in_001', $subscription->stripe_subscription_id));

        $stats = $this->app->make(AdminService::class)->stats();

        $this->assertSame(30899, $stats['payments']['revenue_cents']);
        $this->assertSame(999, $stats['payments']['offers_revenue_cents']);
        $this->assertSame(29900, $stats['payments']['subscriptions_revenue_cents']);
    }

    // Le revenu mensuel recurrent : ce qui rentrera le mois prochain sans
    // qu'aucune vente n'ait lieu. C'est le chiffre qui manquait le plus.
    public function test_the_monthly_recurring_revenue_counts_only_active_subscriptions(): void
    {
        [, $actif] = $this->abonne('rh@nexatech.example.com', 29900);
        [, $impaye] = $this->abonne('rh@autre.example.com', 49900);
        $impaye->update(['status' => SubscriptionStatus::PAST_DUE]);
        [, $resilie] = $this->abonne('contact@ida.example.com', 49900);
        $resilie->update(['status' => SubscriptionStatus::CANCELED]);

        $stats = $this->app->make(AdminService::class)->stats();

        // Seul l'abonnement actif rentrera : un impaye ne rentrera pas, et le
        // compter serait se mentir sur sa propre tresorerie.
        $this->assertSame(29900, $stats['subscriptions']['mrr_cents']);
        $this->assertSame(1, $stats['subscriptions']['active']);
        $this->assertSame(1, $stats['subscriptions']['past_due']);
        $this->assertSame(1, $stats['subscriptions']['canceled']);
        $this->assertSame(1, $stats['subscriptions']['founder_seats_taken']);
        $this->assertSame($actif->id, Subscription::where('status', SubscriptionStatus::ACTIVE)->firstOrFail()->id);
    }

    // ------------------------------------------------------------------
    // LE CABLAGE. Tout ce qui precede appelle handleInvoicePaid en PHP
    // direct : ces tests passeraient encore si l'evenement invoice.paid
    // n'etait branche nulle part dans PaymentService::handleWebhook. C'est
    // exactement l'erreur qui a coute quatre allers-retours en septembre —
    // du code juste, jamais appele. La signature Stripe est donc calculee
    // pour de vrai, et la requete part par la vraie route.
    // ------------------------------------------------------------------

    private function signerCommeStripe(array $corps, string $secret): array
    {
        $payload = json_encode($corps, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $t = time();

        return [
            $payload,
            't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, $secret),
        ];
    }

    public function test_the_stripe_webhook_really_records_a_paid_invoice(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_cablage']);
        [$user, $subscription] = $this->abonne();

        [$payload, $signature] = $this->signerCommeStripe([
            'id' => 'evt_test_1',
            'object' => 'event',
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_webhook',
                'object' => 'invoice',
                'subscription' => $subscription->stripe_subscription_id,
                'amount_paid' => 29900,
                'currency' => 'eur',
                'payment_intent' => 'pi_webhook',
            ]],
        ], 'whsec_test_cablage');

        $this->call(
            'POST',
            '/api/stripe/webhook',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature],
            content: $payload,
        )->assertOk();

        $paiement = Payment::firstOrFail();
        $this->assertSame(PaymentType::SUBSCRIPTION, $paiement->type);
        $this->assertSame(29900, $paiement->amount_cents);
        $this->assertSame($user->id, $paiement->user_id);
    }

    // Contre-epreuve : la meme facture, signee avec un autre secret, ne doit
    // rien enregistrer. Sans elle, le test ci-dessus ne prouverait pas que la
    // verification de signature sert encore a quelque chose.
    public function test_a_badly_signed_invoice_records_nothing(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_cablage']);
        [, $subscription] = $this->abonne();

        [$payload, $signature] = $this->signerCommeStripe([
            'id' => 'evt_test_2',
            'object' => 'event',
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_falsifie',
                'object' => 'invoice',
                'subscription' => $subscription->stripe_subscription_id,
                'amount_paid' => 29900,
                'currency' => 'eur',
            ]],
        ], 'whsec_un_autre_secret');

        $this->call(
            'POST',
            '/api/stripe/webhook',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature],
            content: $payload,
        )->assertStatus(400);

        $this->assertSame(0, Payment::count());
    }

    // Stripe nomme cet evenement invoice.paid OU invoice.payment_succeeded
    // selon la version. Les deux doivent aboutir a la meme recette — et
    // surtout, si Stripe envoie les DEUX pour une meme facture, il ne doit
    // rester qu'une seule ligne. Sans cette garantie, le chiffre d'affaires
    // des abonnements serait double.
    public function test_both_stripe_invoice_event_names_record_exactly_one_payment(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_cablage']);
        [, $subscription] = $this->abonne();

        foreach (['invoice.payment_succeeded', 'invoice.paid'] as $type) {
            [$payload, $signature] = $this->signerCommeStripe([
                'id' => 'evt_'.$type,
                'object' => 'event',
                'type' => $type,
                'data' => ['object' => [
                    'id' => 'in_une_seule_facture',
                    'object' => 'invoice',
                    'subscription' => $subscription->stripe_subscription_id,
                    'amount_paid' => 29900,
                    'currency' => 'eur',
                ]],
            ], 'whsec_test_cablage');

            $this->call(
                'POST',
                '/api/stripe/webhook',
                server: ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature],
                content: $payload,
            )->assertOk();
        }

        $this->assertSame(1, Payment::count());
        $this->assertSame(29900, Payment::firstOrFail()->amount_cents);
    }

    // Un remboursement sort la ligne du CA, comme pour un paiement ponctuel :
    // le total est net, sans quoi il faudrait retrancher a la main.
    public function test_a_refunded_subscription_invoice_leaves_the_revenue(): void
    {
        [, $subscription] = $this->abonne();
        $this->service->handleInvoicePaid($this->facture('in_001', $subscription->stripe_subscription_id));

        Payment::firstOrFail()->update(['status' => PaymentStatus::REFUNDED]);

        $stats = $this->app->make(AdminService::class)->stats();

        $this->assertSame(0, $stats['payments']['revenue_cents']);
        $this->assertSame(0, $stats['payments']['subscriptions_revenue_cents']);
    }
}
