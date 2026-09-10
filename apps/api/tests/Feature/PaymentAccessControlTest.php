<?php

namespace Tests\Feature;

use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\JobOffer;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CfaOrganizationService;
use App\Services\CompanyService;
use App\Services\JobOfferMatchService;
use App\Services\JobOfferService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Qui a le droit de declencher un mouvement d'argent ?
 *
 * PaymentServiceTest eprouve la LOGIQUE du remboursement (garde-fous, etat de
 * l'offre, notification), mais il appelle le service en PHP direct. Il ne dit
 * donc rien de la question posee ici : une entreprise qui vient de payer sa
 * publication peut-elle se rembourser elle-meme ? La reponse ne tient pas dans
 * le service, elle tient dans les middlewares de route — et un middleware ne
 * se verifie qu'en frappant la vraie URL.
 *
 * Tous ces tests passent donc par HTTP. Aucun n'atteint Stripe : le service est
 * substitue dans le conteneur et COMPTE les remboursements tentes, de sorte
 * qu'un controle d'acces defaillant se manifesterait par un remboursement
 * enregistre, et pas seulement par un code HTTP inattendu.
 */
class PaymentAccessControlTest extends TestCase
{
    use RefreshDatabase;

    /** Remboursements Stripe qu'un appel aurait reellement declenches. */
    private array $remboursementsTentes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->remboursementsTentes = [];
        $compteur = function (string $intent) {
            $this->remboursementsTentes[] = $intent;
        };

        // Substitue dans le conteneur, donc utilise par le vrai controleur
        // admin : la requete traverse routes, middlewares et controleur comme
        // en production, seul l'appel reseau a Stripe est neutralise.
        $this->app->bind(PaymentService::class, fn ($app) => new class($app->make(JobOfferService::class), $app->make(SubscriptionService::class), $app->make(JobOfferMatchService::class), $compteur) extends PaymentService
        {
            public function __construct($jobOfferService, $subscriptionService, $matchService, private $compteur)
            {
                parent::__construct($jobOfferService, $subscriptionService, $matchService);
            }

            protected function performStripeRefund(string $paymentIntentId): void
            {
                ($this->compteur)($paymentIntentId);
            }
        });
    }

    private function company(string $email = 'rh@nexatech.example.com'): User
    {
        $user = User::create(['email' => $email, 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
        $this->app->make(CompanyService::class)->createForUser($user, ['name' => 'NexaTech']);

        return $user->fresh();
    }

    private function cfa(): User
    {
        $user = User::create(['email' => 'contact@ida.example.com', 'password_hash' => 'x', 'role' => UserRole::CFA]);
        $this->app->make(CfaOrganizationService::class)->createForUser($user, ['name' => 'IDA']);

        return $user->fresh();
    }

    private function candidate(): User
    {
        return User::create(['email' => 'lea@example.com', 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);
    }

    private function admin(): User
    {
        return User::create(['email' => 'admin@jeuncy.com', 'password_hash' => 'x', 'role' => UserRole::ADMIN]);
    }

    private function draftOfferFor(User $owner): JobOffer
    {
        return $this->app->make(JobOfferService::class)->createForUser($owner, [
            'title' => 'Developpeur web full-stack en alternance',
            'description' => 'Rejoins notre equipe.',
            'contract_type' => ContractType::ALTERNANCE->value,
        ]);
    }

    /** Une offre payee et publiee, telle qu'elle existe apres un vrai achat. */
    private function paidOfferFor(User $owner): array
    {
        $offer = $this->draftOfferFor($owner);

        Payment::create([
            'user_id' => $owner->id,
            'job_offer_id' => $offer->id,
            'amount_cents' => 999,
            'currency' => 'EUR',
            'status' => PaymentStatus::PENDING,
            'stripe_session_id' => 'cs_test_'.$owner->id,
        ]);

        $this->app->make(PaymentService::class)
            ->markPaymentSucceeded('cs_test_'.$owner->id, 'pi_test_'.$owner->id);

        $payment = Payment::where('stripe_session_id', 'cs_test_'.$owner->id)->firstOrFail();

        return [$offer->fresh(), $payment];
    }

    // ---------------------------------------------------------------------
    // Remboursement. « Payer, publier, se faire rembourser, garder
    // l'annonce » ne doit etre possible pour personne d'autre qu'un admin.
    // ---------------------------------------------------------------------

    public function test_a_company_cannot_refund_its_own_payment(): void
    {
        $company = $this->company();
        [$offer, $payment] = $this->paidOfferFor($company);

        $this->actingAs($company, 'api')
            ->postJson("/api/admin/payments/{$payment->id}/refund")
            ->assertStatus(403);

        // Le code HTTP ne suffit pas : ce qui compte est qu'aucun euro n'ait
        // bouge et que rien n'ait ete entame au passage.
        $this->assertSame([], $this->remboursementsTentes);
        $this->assertSame(PaymentStatus::SUCCEEDED, $payment->fresh()->status);
        $this->assertSame(JobOfferStatus::PUBLISHED, $offer->fresh()->status);
    }

    public function test_a_cfa_cannot_refund_its_own_payment(): void
    {
        $cfa = $this->cfa();
        [, $payment] = $this->paidOfferFor($cfa);

        $this->actingAs($cfa, 'api')
            ->postJson("/api/admin/payments/{$payment->id}/refund")
            ->assertStatus(403);

        $this->assertSame([], $this->remboursementsTentes);
        $this->assertSame(PaymentStatus::SUCCEEDED, $payment->fresh()->status);
    }

    public function test_a_company_cannot_refund_another_companys_payment(): void
    {
        $victime = $this->company('rh@nexatech.example.com');
        [, $payment] = $this->paidOfferFor($victime);

        $this->actingAs($this->company('rh@autre.example.com'), 'api')
            ->postJson("/api/admin/payments/{$payment->id}/refund")
            ->assertStatus(403);

        $this->assertSame([], $this->remboursementsTentes);
        $this->assertSame(PaymentStatus::SUCCEEDED, $payment->fresh()->status);
    }

    public function test_a_candidate_cannot_refund_a_payment(): void
    {
        [, $payment] = $this->paidOfferFor($this->company());

        $this->actingAs($this->candidate(), 'api')
            ->postJson("/api/admin/payments/{$payment->id}/refund")
            ->assertStatus(403);

        $this->assertSame([], $this->remboursementsTentes);
    }

    public function test_an_anonymous_visitor_cannot_refund_a_payment(): void
    {
        [, $payment] = $this->paidOfferFor($this->company());

        $this->postJson("/api/admin/payments/{$payment->id}/refund")->assertStatus(401);

        $this->assertSame([], $this->remboursementsTentes);
    }

    // Contre-epreuve indispensable : sans elle, tous les tests ci-dessus
    // passeraient encore si la route avait purement disparu.
    public function test_an_admin_can_still_refund(): void
    {
        $company = $this->company();
        [$offer, $payment] = $this->paidOfferFor($company);

        $this->actingAs($this->admin(), 'api')
            ->postJson("/api/admin/payments/{$payment->id}/refund")
            ->assertOk();

        $this->assertSame(["pi_test_{$company->id}"], $this->remboursementsTentes);
        $this->assertSame(PaymentStatus::REFUNDED, $payment->fresh()->status);
        // Rembourse = annonce retiree, sinon le service serait offert.
        $this->assertSame(JobOfferStatus::ARCHIVED, $offer->fresh()->status);
    }

    public function test_a_company_cannot_read_the_payments_of_the_whole_platform(): void
    {
        $this->actingAs($this->company(), 'api')
            ->getJson('/api/admin/payments')
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------------
    // Paiement a l'annonce
    // ---------------------------------------------------------------------

    // Sans cette garde, une entreprise ferait publier le brouillon d'une autre
    // en payant a sa place — et decouvrirait au passage des offres qui ne la
    // regardent pas en balayant les identifiants.
    public function test_a_company_cannot_pay_for_another_companys_offer(): void
    {
        $offre = $this->draftOfferFor($this->company('rh@nexatech.example.com'));

        $this->actingAs($this->company('rh@autre.example.com'), 'api')
            ->postJson("/api/job-offers/{$offre->id}/checkout")
            ->assertStatus(403);

        $this->assertSame(JobOfferStatus::DRAFT, $offre->fresh()->status);
    }

    public function test_a_candidate_cannot_open_a_checkout(): void
    {
        $offre = $this->draftOfferFor($this->company());

        $this->actingAs($this->candidate(), 'api')
            ->postJson("/api/job-offers/{$offre->id}/checkout")
            ->assertStatus(403);
    }

    public function test_a_company_only_sees_its_own_payments(): void
    {
        $mien = $this->company('rh@nexatech.example.com');
        [, $monPaiement] = $this->paidOfferFor($mien);
        [, $sonPaiement] = $this->paidOfferFor($this->company('rh@autre.example.com'));

        $reponse = $this->actingAs($mien, 'api')->getJson('/api/payments/mine')->assertOk();

        $ids = collect($reponse->json('data'))->pluck('id')->all();
        $this->assertContains($monPaiement->id, $ids);
        $this->assertNotContains($sonPaiement->id, $ids);
    }

    // ---------------------------------------------------------------------
    // Webhook Stripe. C'est la seule route qui publie une offre sans qu'un
    // utilisateur authentifie le demande : si une signature falsifiee
    // passait, n'importe qui publierait gratuitement, en boucle.
    // ---------------------------------------------------------------------

    public function test_a_forged_webhook_cannot_mark_a_payment_as_paid(): void
    {
        Config::set('services.stripe.webhook_secret', 'whsec_test');

        $company = $this->company();
        $offre = $this->draftOfferFor($company);
        $paiement = Payment::create([
            'user_id' => $company->id,
            'job_offer_id' => $offre->id,
            'amount_cents' => 999,
            'currency' => 'EUR',
            'status' => PaymentStatus::PENDING,
            'stripe_session_id' => 'cs_test_forge',
        ]);

        $this->postJson('/api/stripe/webhook', [
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_forge', 'payment_intent' => 'pi_forge']],
        ], ['Stripe-Signature' => 't=1,v1=faux'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_WEBHOOK_SIGNATURE');

        $this->assertSame(PaymentStatus::PENDING, $paiement->fresh()->status);
        $this->assertSame(JobOfferStatus::DRAFT, $offre->fresh()->status);
    }

    public function test_a_webhook_without_any_signature_is_refused(): void
    {
        Config::set('services.stripe.webhook_secret', 'whsec_test');

        $this->postJson('/api/stripe/webhook', ['type' => 'checkout.session.completed'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_WEBHOOK_SIGNATURE');
    }

    // ---------------------------------------------------------------------
    // Abonnement
    // ---------------------------------------------------------------------

    public function test_a_candidate_cannot_subscribe(): void
    {
        $this->actingAs($this->candidate(), 'api')
            ->postJson('/api/subscriptions/checkout')
            ->assertStatus(403);
    }

    public function test_a_candidate_cannot_read_a_subscription(): void
    {
        $this->actingAs($this->candidate(), 'api')
            ->getJson('/api/subscriptions/mine')
            ->assertStatus(403);
    }

    // Resilier n'est pas rembourser : la route de resiliation ne doit jamais
    // servir de porte derobee vers un mouvement d'argent.
    public function test_cancelling_without_a_subscription_never_touches_money(): void
    {
        $this->actingAs($this->company(), 'api')
            ->postJson('/api/subscriptions/cancel')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'SUBSCRIPTION_NOT_FOUND');

        $this->assertSame([], $this->remboursementsTentes);
    }

    // Le compteur de places fondateur est public (page Tarifs, visiteur sans
    // compte) : il ne doit reveler que des nombres, jamais un client.
    public function test_the_public_founder_counter_leaks_no_customer(): void
    {
        $company = $this->company();
        Subscription::create([
            'user_id' => $company->id,
            'stripe_subscription_id' => 'sub_test_1',
            'stripe_customer_id' => 'cus_test_1',
            'status' => SubscriptionStatus::ACTIVE,
            'amount_cents' => 29900,
            'currency' => 'EUR',
            'is_founder_rate' => true,
        ]);

        $reponse = $this->getJson('/api/subscriptions/founder-offer')->assertOk();

        $this->assertSame(1, $reponse->json('data.seats_taken'));
        $reponse->assertJsonMissing(['email' => $company->email]);
    }
}
