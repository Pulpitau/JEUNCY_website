<?php

namespace Tests\Feature;

use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CompanyService;
use App\Services\JobOfferMatchService;
use App\Services\JobOfferService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le paiement a l'offre achete une PERIODE, plus une publication definitive.
 *
 * Decision du 2026-09-10 : 9,99 EUR (5,99 EUR pour un CFA) mettent l'annonce
 * en ligne un mois. Passe ce delai elle sort de la ligne, et son proprietaire
 * la remet en ligne en repayant. Aucun prelevement automatique — ce n'est pas
 * un abonnement Stripe, c'est un achat ponctuel qui expire.
 *
 * Ce fichier couvre le cycle complet, et surtout ce qui NE doit PAS expirer :
 * l'essai gratuit et l'abonnement illimite. Une offre retiree a tort au client
 * qui paie 499 EUR par mois serait le plus couteux des defauts possibles, et
 * c'est le plus facile a introduire ici.
 */
class OfferMonthlyPublicationTest extends TestCase
{
    use RefreshDatabase;

    private JobOfferService $offres;

    private PaymentService $paiements;

    protected function setUp(): void
    {
        parent::setUp();

        $this->offres = $this->app->make(JobOfferService::class);
        $this->paiements = $this->app->make(PaymentService::class);
    }

    private function entreprise(string $email = 'rh@nexatech.example.com'): User
    {
        $user = User::create(['email' => $email, 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
        $this->app->make(CompanyService::class)->createForUser($user, ['name' => 'NexaTech']);

        return $user->fresh();
    }

    private function brouillon(User $owner, string $titre = 'Developpeur web en alternance'): JobOffer
    {
        return $this->offres->createForUser($owner, [
            'title' => $titre,
            'description' => 'Rejoins notre equipe.',
            'contract_type' => ContractType::ALTERNANCE->value,
        ]);
    }

    /** Simule un encaissement Stripe reussi sur cette offre. */
    private function encaisser(User $owner, JobOffer $offre, string $reference): JobOffer
    {
        Payment::create([
            'user_id' => $owner->id,
            'job_offer_id' => $offre->id,
            'amount_cents' => 999,
            'currency' => 'EUR',
            'status' => PaymentStatus::PENDING,
            'stripe_session_id' => 'cs_'.$reference,
        ]);

        $this->paiements->markPaymentSucceeded('cs_'.$reference, 'pi_'.$reference);

        return $offre->fresh();
    }

    private function abonner(User $user): void
    {
        Subscription::create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::ACTIVE,
            'amount_cents' => 49900,
            'stripe_subscription_id' => 'sub_'.$user->id,
            'stripe_customer_id' => 'cus_'.$user->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Le cycle nominal
    // ------------------------------------------------------------------

    public function test_paying_puts_the_offer_online_for_one_month(): void
    {
        $owner = $this->entreprise();
        $offre = $this->encaisser($owner, $this->brouillon($owner), 'initial');

        $this->assertSame(JobOfferStatus::PUBLISHED, $offre->status);
        $this->assertNotNull($offre->expires_at);
        $this->assertEqualsWithDelta(30, now()->diffInDays($offre->expires_at, false), 1);
    }

    public function test_the_offer_goes_offline_once_the_month_is_over(): void
    {
        $owner = $this->entreprise();
        $offre = $this->encaisser($owner, $this->brouillon($owner), 'initial');
        $offre->update(['expires_at' => now()->subDay()]);

        $this->artisan('job-offers:expire')->assertSuccessful();

        $this->assertSame(JobOfferStatus::EXPIRED, $offre->fresh()->status);
    }

    // Sans cela, une offre echue serait un cul-de-sac : son proprietaire ne
    // pourrait plus jamais la remettre en ligne, donc plus jamais payer.
    public function test_an_expired_offer_can_be_paid_again(): void
    {
        $owner = $this->entreprise();
        $offre = $this->encaisser($owner, $this->brouillon($owner), 'initial');
        $offre->update(['status' => JobOfferStatus::EXPIRED, 'expires_at' => now()->subDay()]);

        $payable = $this->offres->requirePayableOffer($owner, $offre->fresh());

        $this->assertSame($offre->id, $payable->id);
    }

    // Un renouvellement repart pour une periode PLEINE, jamais pour le
    // reliquat de la precedente : le client paie un mois, il obtient un mois.
    public function test_renewing_restarts_a_full_period(): void
    {
        $owner = $this->entreprise();
        $offre = $this->encaisser($owner, $this->brouillon($owner), 'initial');
        $offre->update(['status' => JobOfferStatus::EXPIRED, 'expires_at' => now()->subDays(10)]);

        $renouvelee = $this->encaisser($owner, $offre->fresh(), 'renouvellement');

        $this->assertSame(JobOfferStatus::PUBLISHED, $renouvelee->status);
        $this->assertEqualsWithDelta(30, now()->diffInDays($renouvelee->expires_at, false), 1);
    }

    // Une offre archivee A LA MAIN reste non payable : son proprietaire l'a
    // retiree volontairement, la regle d'origine ne change pas.
    public function test_a_manually_archived_offer_stays_unpayable(): void
    {
        $owner = $this->entreprise();
        $offre = $this->encaisser($owner, $this->brouillon($owner), 'initial');
        $offre->update(['status' => JobOfferStatus::ARCHIVED]);

        $this->expectExceptionMessage('Cette offre ne peut pas etre payee dans son etat actuel.');
        $this->offres->requirePayableOffer($owner, $offre->fresh());
    }

    // ------------------------------------------------------------------
    // Ce qui ne doit JAMAIS expirer
    // ------------------------------------------------------------------

    public function test_an_offer_published_via_subscription_never_expires(): void
    {
        $owner = $this->entreprise();
        $this->abonner($owner);
        $offre = $this->brouillon($owner);
        $this->app->make(SubscriptionService::class);
        $this->offres->publishViaSubscriptionForUser($owner->fresh(), $offre);

        $this->assertNull($offre->fresh()->expires_at);

        $this->artisan('job-offers:expire')->assertSuccessful();
        $this->assertSame(JobOfferStatus::PUBLISHED, $offre->fresh()->status);
    }

    // Le piege : requirePayableOffer accepte desormais une offre EXPIRED, donc
    // un abonne peut republier une offre anciennement payee a l'unite. Si son
    // ancienne echeance survivait, l'offre du client le plus cher retomberait
    // hors ligne des la nuit suivante.
    public function test_a_subscriber_republishing_an_expired_offer_clears_its_expiry(): void
    {
        $owner = $this->entreprise();
        $offre = $this->encaisser($owner, $this->brouillon($owner), 'initial');
        $offre->update(['status' => JobOfferStatus::EXPIRED, 'expires_at' => now()->subDays(5)]);
        $this->abonner($owner);

        $this->offres->publishViaSubscriptionForUser($owner->fresh(), $offre->fresh());

        $this->assertNull($offre->fresh()->expires_at);

        $this->artisan('job-offers:expire')->assertSuccessful();
        $this->assertSame(JobOfferStatus::PUBLISHED, $offre->fresh()->status);
    }

    // Une entreprise qui souscrit alors qu'elle a des offres payees a l'unite
    // encore en cours ne doit pas les voir tomber : elle paie desormais pour la
    // publication illimitee.
    public function test_an_offer_is_not_taken_down_while_its_owner_subscribes(): void
    {
        $owner = $this->entreprise();
        $offre = $this->encaisser($owner, $this->brouillon($owner), 'initial');
        $offre->update(['expires_at' => now()->subDay()]);
        $this->abonner($owner);

        $this->artisan('job-offers:expire')->assertSuccessful();

        $this->assertSame(JobOfferStatus::PUBLISHED, $offre->fresh()->status);
    }

    public function test_a_trial_offer_is_not_touched_by_the_monthly_expiry(): void
    {
        $owner = $this->entreprise();
        $offre = $this->brouillon($owner);
        $this->offres->publishViaTrialForUser($owner, $offre);
        // Meme avec une echeance parasite, l'essai releve d'une autre commande.
        $offre->update(['expires_at' => now()->subDay()]);

        $this->artisan('job-offers:expire')->assertSuccessful();

        $this->assertSame(JobOfferStatus::PUBLISHED, $offre->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Prevenir le proprietaire
    // ------------------------------------------------------------------

    public function test_the_owner_is_warned_before_the_offer_goes_offline(): void
    {
        $owner = $this->entreprise();
        $offre = $this->encaisser($owner, $this->brouillon($owner), 'initial');
        $offre->update(['expires_at' => now()->addDays(2)]);

        $this->artisan('job-offers:expire')->assertSuccessful();

        $preavis = Notification::where('user_id', $owner->id)
            ->where('type', NotificationType::JOB_OFFER_EXPIRING->value)
            ->first();

        $this->assertNotNull($preavis, "Le proprietaire n'est pas prevenu avant que son annonce disparaisse.");
        $this->assertStringContainsString($offre->title, $preavis->message);
        $this->assertSame(JobOfferStatus::PUBLISHED, $offre->fresh()->status);
    }

    // Le cron passe chaque jour : sans deduplication, le preavis de trois jours
    // partirait trois fois. Un client prevenu trois fois cesse de lire.
    public function test_the_warning_is_sent_only_once_per_paid_period(): void
    {
        $owner = $this->entreprise();
        $offre = $this->encaisser($owner, $this->brouillon($owner), 'initial');
        $offre->update(['expires_at' => now()->addDays(2)]);

        $this->artisan('job-offers:expire')->assertSuccessful();
        $this->artisan('job-offers:expire')->assertSuccessful();
        $this->artisan('job-offers:expire')->assertSuccessful();

        $this->assertSame(1, Notification::where('user_id', $owner->id)
            ->where('type', NotificationType::JOB_OFFER_EXPIRING->value)->count());
    }

    // ... mais la periode SUIVANTE en redeclenche un : la deduplication porte
    // sur la date de fin, pas sur l'offre.
    public function test_a_new_paid_period_earns_a_new_warning(): void
    {
        $owner = $this->entreprise();
        $offre = $this->encaisser($owner, $this->brouillon($owner), 'initial');
        $offre->update(['expires_at' => now()->addDays(2)]);
        $this->artisan('job-offers:expire')->assertSuccessful();

        // Renouvellement : nouvelle echeance, donc nouveau preavis a terme.
        $offre->fresh()->update(['expires_at' => now()->addDays(3)]);
        $this->artisan('job-offers:expire')->assertSuccessful();

        $this->assertSame(2, Notification::where('user_id', $owner->id)
            ->where('type', NotificationType::JOB_OFFER_EXPIRING->value)->count());
    }

    public function test_the_owner_is_told_when_the_offer_goes_offline(): void
    {
        $owner = $this->entreprise();
        $offre = $this->encaisser($owner, $this->brouillon($owner), 'initial');
        $offre->update(['expires_at' => now()->subDay()]);

        $this->artisan('job-offers:expire')->assertSuccessful();

        $avis = Notification::where('user_id', $owner->id)
            ->where('type', NotificationType::JOB_OFFER_EXPIRING->value)
            ->where('link', '/mes-offres')
            ->first();

        $this->assertNotNull($avis, "L'annonce sort de la ligne sans que personne ne le dise.");
        $this->assertStringContainsString('9,99', $avis->message);
    }

    // ------------------------------------------------------------------
    // Remboursement
    // ------------------------------------------------------------------

    // Le client se fait rembourser un geste commercial : il ne doit pas y
    // perdre la possibilite de remettre son annonce en ligne.
    public function test_refunding_an_expired_offer_keeps_it_repayable(): void
    {
        $owner = $this->entreprise();
        $offre = $this->encaisser($owner, $this->brouillon($owner), 'initial');
        $offre->update(['status' => JobOfferStatus::EXPIRED, 'expires_at' => now()->subDay()]);
        $paiement = Payment::where('stripe_session_id', 'cs_initial')->firstOrFail();

        $this->serviceSansStripe()->refund($paiement);

        $this->assertSame(JobOfferStatus::EXPIRED, $offre->fresh()->status);
        $this->assertNull($offre->fresh()->expires_at);
        $this->assertSame($offre->id, $this->offres->requirePayableOffer($owner, $offre->fresh())->id);
    }

    public function test_refunding_a_live_offer_clears_its_period(): void
    {
        $owner = $this->entreprise();
        $offre = $this->encaisser($owner, $this->brouillon($owner), 'initial');
        $paiement = Payment::where('stripe_session_id', 'cs_initial')->firstOrFail();

        $this->serviceSansStripe()->refund($paiement);

        $this->assertSame(JobOfferStatus::ARCHIVED, $offre->fresh()->status);
        $this->assertNull($offre->fresh()->expires_at);
    }

    private function serviceSansStripe(): PaymentService
    {
        return new class($this->app->make(JobOfferService::class), $this->app->make(SubscriptionService::class), $this->app->make(JobOfferMatchService::class)) extends PaymentService
        {
            protected function performStripeRefund(string $paymentIntentId): void {}
        };
    }

    // Sur l'ecran meme ou on invite le client a repayer, un bouton voisin
    // ne doit pas pouvoir detruire cette possibilite sans un mot.
    public function test_an_expired_offer_cannot_be_archived_into_a_dead_end(): void
    {
        $owner = $this->entreprise();
        $offre = $this->encaisser($owner, $this->brouillon($owner), 'initial');
        $offre->update(['status' => JobOfferStatus::EXPIRED]);

        try {
            $this->offres->archiveForUser($owner, $offre->fresh());
            $this->fail('Archiver une offre echue devrait etre refuse.');
        } catch (ApiException $e) {
            $this->assertStringContainsString("n'est deja plus en ligne", $e->getMessage());
        }

        // Et elle reste remettable en ligne, c'est tout l'enjeu.
        $this->assertSame(JobOfferStatus::EXPIRED, $offre->fresh()->status);
        $this->assertSame($offre->id, $this->offres->requirePayableOffer($owner, $offre->fresh())->id);
    }

    // ------------------------------------------------------------------
    // Les candidats
    // ------------------------------------------------------------------

    // Une annonce remise en ligne douze mois de suite ne doit pas envoyer douze
    // fois la meme notification aux memes jeunes.
    public function test_candidates_are_not_notified_twice_when_an_offer_is_renewed(): void
    {
        $owner = $this->entreprise();
        $offre = $this->brouillon($owner);
        $offre->update(['city' => 'Perpignan']);

        $candidat = User::create(['email' => 'lea@example.com', 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);
        CandidateProfile::create([
            'user_id' => $candidat->id,
            'first_name' => 'Lea',
            'last_name' => 'Girard',
            'city' => 'Perpignan',
        ]);

        $this->encaisser($owner, $offre->fresh(), 'initial');
        $premier = Notification::where('user_id', $candidat->id)
            ->where('type', NotificationType::JOB_OFFER_MATCH->value)->count();

        $offre->fresh()->update(['status' => JobOfferStatus::EXPIRED, 'expires_at' => now()->subDay()]);
        $this->encaisser($owner, $offre->fresh(), 'renouvellement');

        $this->assertSame(1, $premier, 'Le candidat aurait du etre prevenu a la premiere mise en ligne.');
        $this->assertSame(1, Notification::where('user_id', $candidat->id)
            ->where('type', NotificationType::JOB_OFFER_MATCH->value)->count());
    }
}
