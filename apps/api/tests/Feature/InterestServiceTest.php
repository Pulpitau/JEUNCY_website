<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\InterestDecision;
use App\Enums\JobOfferStatus;
use App\Enums\NotificationType;
use App\Exceptions\ApiException;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\Notification;
use App\Models\OfferInterest;
use App\Models\User;
use App\Services\BlockService;
use App\Services\DiscoverService;
use App\Services\InterestService;
use App\Services\MailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\AideMatch;
use Tests\TestCase;

/**
 * « Ca m'interesse », « Passer », quotas et naissance du match.
 *
 * REGLE QUI GOUVERNE TOUT CE FICHIER : un LIKE de candidat seul ne fait
 * RIEN apparaitre chez l'employeur (sinon la CVtheque se remplirait de
 * noms sans que personne n'ait dit oui), et un LIKE d'employeur seul
 * previent le candidat sans email (l'offre remonte dans sa pile, c'est le
 * vrai canal).
 */
class InterestServiceTest extends TestCase
{
    use AideMatch;
    use RefreshDatabase;

    private InterestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chargerLesRoutesDuMatch();
        $this->ouvrirLePerimetre();
        $this->service = $this->app->make(InterestService::class);
    }

    public function test_candidate_like_alone_notifies_nobody_at_employer(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        $reponse = $this->service->like($candidat, $offre);

        $this->assertFalse($reponse['matched']);
        $this->assertSame(InterestDecision::LIKE, $reponse['interest']['decision']);
        $this->assertSame(0, Notification::where('user_id', $employeur->id)->count());
    }

    public function test_employer_like_alone_notifies_candidate_in_app_only(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        $mail = Mockery::mock(MailService::class);
        $mail->shouldNotReceive('sendNewMatchEmail');
        $this->app->instance(MailService::class, $mail);

        $this->app->make(InterestService::class)->like($employeur, $offre, $this->profilDe($candidat));

        $notification = Notification::where('user_id', $candidat->id)->firstOrFail();
        $this->assertSame(NotificationType::INTEREST_RECEIVED, $notification->type);
        $this->assertSame('/offres/'.$offre->id, $notification->link);
    }

    public function test_employer_like_on_a_passed_candidate_notifies_nobody(): void
    {
        $candidat = $this->candidat();
        $profil = $this->profilDe($candidat);
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        OfferInterest::factory()->candidatePassed()
            ->create(['candidate_profile_id' => $profil->id, 'job_offer_id' => $offre->id]);

        // La cible n'est plus eligible : le deck ne la propose plus, et un
        // identifiant devine ne doit pas contourner cette regle.
        $this->expectExceptionMessage("Ce candidat n'est plus disponible.");
        $this->service->like($employeur, $offre, $profil);
    }

    public function test_double_like_creates_match_once(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        $this->service->like($employeur, $offre, $this->profilDe($candidat));
        $premier = $this->service->like($candidat, $offre);
        $rejoue = $this->service->like($candidat, $offre);

        $this->assertTrue($premier['matched']);
        $this->assertTrue($rejoue['matched']);
        $this->assertSame(1, OfferInterest::whereNotNull('matched_at')->count());
        $this->assertSame(
            1,
            Notification::where('user_id', $candidat->id)->where('type', NotificationType::NEW_MATCH)->count(),
        );
    }

    public function test_match_notifies_and_emails_both(): void
    {
        $candidat = $this->candidat([], ['email' => 'lea@example.test']);
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        $mail = Mockery::mock(MailService::class);
        $mail->shouldReceive('sendNewMatchEmail')->twice();
        $this->app->instance(MailService::class, $mail);

        $this->app->make(InterestService::class)->like($employeur, $offre, $this->profilDe($candidat));
        $this->app->make(InterestService::class)->like($candidat, $offre);

        $this->assertSame(1, Notification::where('user_id', $candidat->id)
            ->where('type', NotificationType::NEW_MATCH)->count());
        $this->assertSame(1, Notification::where('user_id', $employeur->id)
            ->where('type', NotificationType::NEW_MATCH)->count());

        $ligne = OfferInterest::firstOrFail();
        $this->assertNotNull($ligne->candidate_notified_at);
        $this->assertNotNull($ligne->employer_notified_at);
    }

    public function test_match_attaches_existing_application_without_invite(): void
    {
        $candidat = $this->candidat();
        $profil = $this->profilDe($candidat);
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        $candidature = $profil->applications()->create([
            'job_offer_id' => $offre->id,
            'status' => ApplicationStatus::SENT,
        ]);
        // Le candidat avait deja dit oui en postulant : seul l'employeur
        // reste a decider.
        OfferInterest::factory()->candidateLiked()->create([
            'candidate_profile_id' => $profil->id,
            'job_offer_id' => $offre->id,
            'application_id' => $candidature->id,
        ]);

        $reponse = $this->service->like($employeur, $offre, $profil);

        $this->assertTrue($reponse['matched']);
        $this->assertSame($candidature->id, $reponse['interest']['application_id']);

        $message = Notification::where('user_id', $candidat->id)
            ->where('type', NotificationType::NEW_MATCH)->value('message');
        $this->assertStringNotContainsString('Envoie ton dossier', $message);
    }

    public function test_response_never_carries_the_other_side_decision(): void
    {
        $candidat = $this->candidat();
        $profil = $this->profilDe($candidat);
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        OfferInterest::factory()->employerPassed()
            ->create(['candidate_profile_id' => $profil->id, 'job_offer_id' => $offre->id]);

        $reponse = $this->service->like($candidat, $offre);

        $this->assertSame(
            ['id', 'job_offer_id', 'candidate_profile_id', 'decision', 'decided_at', 'matched_at', 'application_id'],
            array_keys($reponse['interest']),
        );
        $this->assertSame(InterestDecision::LIKE, $reponse['interest']['decision']);
    }

    public function test_different_decision_is_409(): void
    {
        $candidat = $this->candidat();
        $offre = $this->offrePubliee($this->employeur());

        $this->service->passBatch($candidat, [$offre->id]);

        try {
            $this->service->like($candidat, $offre);
            $this->fail('Une decision contraire doit etre refusee.');
        } catch (ApiException $e) {
            $this->assertSame('INTEREST_ALREADY_DECIDED', $e->errorCode);
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    public function test_like_on_unpublished_offer_409(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $offre = JobOffer::factory()->located()->create([
            'company_id' => $employeur->company->id,
            'status' => JobOfferStatus::DRAFT,
        ]);

        try {
            $this->service->like($candidat, $offre);
            $this->fail('Un brouillon ne doit pas pouvoir etre like.');
        } catch (ApiException $e) {
            $this->assertSame('JOB_OFFER_NOT_PUBLISHED', $e->errorCode);
        }
    }

    public function test_employer_like_requires_owned_offer_verified_and_open_department(): void
    {
        $candidat = $this->candidat();
        $profil = $this->profilDe($candidat);
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        // Offre d'un autre.
        $etranger = $this->employeur();
        $this->assertCode('FORBIDDEN', fn () => $this->service->like($etranger, $offre, $profil));

        // Entreprise non verifiee.
        $nonVerifiee = $this->employeur(verifiee: false);
        $sonOffre = $this->offrePubliee($nonVerifiee);
        $this->assertCode('COMPANY_NOT_VERIFIED', fn () => $this->service->like($nonVerifiee, $sonOffre, $profil));

        // Departement ferme.
        $this->ouvrirLePerimetre('11');
        $this->assertCode('MATCH_NOT_OPEN_HERE', fn () => $this->service->like($employeur, $offre, $profil));
    }

    public function test_blocked_user_cannot_like(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        app(BlockService::class)->block($employeur, $candidat->id);

        $this->assertCode('USER_BLOCKED', fn () => $this->service->like($candidat, $offre));
    }

    public function test_candidate_quota_inactive_under_20_offers_in_range(): void
    {
        $candidat = $this->candidat();
        $profil = $this->profilDe($candidat);
        $employeur = $this->employeur();

        // 16 offres encore a decider : sous le seuil de 20, le quota ne
        // s'active pas, meme avec 20 LIKE deja poses dans la journee.
        // Plafonner quelqu'un qui n'a presque rien a voir le bloquerait sans
        // rien proteger — c'est exactement la situation de la production, qui
        // ne compte qu'une offre Jeuncy publiee.
        JobOffer::factory()->count(15)->published()->located()
            ->create(['company_id' => $employeur->company->id]);
        $cible = $this->offrePubliee($employeur);

        for ($i = 0; $i < 20; $i++) {
            OfferInterest::factory()->candidateLiked()->create([
                'candidate_profile_id' => $profil->id,
                'job_offer_id' => JobOffer::factory()->published()->located()
                    ->create(['company_id' => $employeur->company->id])->id,
            ]);
        }

        $quota = $this->app->make(DiscoverService::class)->quotaFor($profil);
        $this->assertFalse($quota['active']);
        $this->assertSame(20, $quota['used']);

        // Le 21e geste passe quand meme : c'est le sens de « inactif ».
        $reponse = $this->service->like($candidat, $cible);
        $this->assertSame(InterestDecision::LIKE, $reponse['interest']['decision']);
    }

    public function test_candidate_quota_20_per_sliding_day(): void
    {
        $candidat = $this->candidat();
        $profil = $this->profilDe($candidat);
        $employeur = $this->employeur();

        // 30 offres a portee : le quota s'active.
        JobOffer::factory()->count(30)->published()->located()
            ->create(['company_id' => $employeur->company->id]);

        // 20 LIKE dans les 24 h, plus des PASS et un LIKE d'hier, qui ne
        // comptent pas.
        for ($i = 0; $i < 20; $i++) {
            OfferInterest::factory()->candidateLiked()->create([
                'candidate_profile_id' => $profil->id,
                'job_offer_id' => JobOffer::factory()->published()->located()
                    ->create(['company_id' => $employeur->company->id])->id,
            ]);
        }
        OfferInterest::factory()->candidatePassed()->create([
            'candidate_profile_id' => $profil->id,
            'job_offer_id' => JobOffer::factory()->published()->located()
                ->create(['company_id' => $employeur->company->id])->id,
        ]);
        OfferInterest::factory()->candidateLiked()->create([
            'candidate_profile_id' => $profil->id,
            'candidate_decided_at' => now()->subDays(2),
            'job_offer_id' => JobOffer::factory()->published()->located()
                ->create(['company_id' => $employeur->company->id])->id,
        ]);

        $cible = $this->offrePubliee($employeur);

        try {
            $this->service->like($candidat, $cible);
            $this->fail('Le 21e « Ca m interesse » du jour doit etre refuse.');
        } catch (ApiException $e) {
            $this->assertSame('INTEREST_QUOTA_REACHED', $e->errorCode);
            $this->assertSame(429, $e->getStatusCode());
        }
    }

    public function test_employer_quota_30_per_offer(): void
    {
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);
        $autreOffre = $this->offrePubliee($employeur);

        for ($i = 0; $i < 30; $i++) {
            OfferInterest::factory()->employerLiked()->create([
                'candidate_profile_id' => CandidateProfile::factory()->adult()->located()
                    ->create(['user_id' => User::factory()->candidate()])->id,
                'job_offer_id' => $offre->id,
            ]);
        }

        $cible = $this->profilDe($this->candidat());

        $this->assertCode('INTEREST_QUOTA_REACHED', fn () => $this->service->like($employeur, $offre, $cible));

        // Par offre, pas par compte : l'autre offre n'est pas bloquee.
        $this->assertTrue(is_array($this->service->like($employeur, $autreOffre, $cible)));
    }

    public function test_pass_batch_never_overwrites_a_decision(): void
    {
        $candidat = $this->candidat();
        $profil = $this->profilDe($candidat);
        $employeur = $this->employeur();

        $deja = $this->offrePubliee($employeur);
        $neuve = $this->offrePubliee($employeur);
        OfferInterest::factory()->candidateLiked()
            ->create(['candidate_profile_id' => $profil->id, 'job_offer_id' => $deja->id]);

        $poses = $this->service->passBatch($candidat, [$deja->id, $neuve->id]);

        $this->assertSame(1, $poses);
        $this->assertSame(
            InterestDecision::LIKE,
            OfferInterest::where('job_offer_id', $deja->id)->value('candidate_decision'),
        );
    }

    public function test_pass_batch_employer_requires_owned_offer(): void
    {
        $offre = $this->offrePubliee($this->employeur());
        $etranger = $this->employeur();
        $profil = $this->profilDe($this->candidat());

        $this->assertCode('FORBIDDEN', fn () => $this->service->passBatch($etranger, [$offre->id], [$profil->id]));
    }

    public function test_candidate_body_profile_id_is_ignored(): void
    {
        // Le corps de la requete porte le profil d'un AUTRE candidat : le
        // service doit poser le geste sur celui de l'appelant.
        $candidat = $this->candidat([], ['email' => 'moi@example.test']);
        $autre = $this->candidat([], ['email' => 'autre@example.test']);
        $offre = $this->offrePubliee($this->employeur());

        $this->withToken($this->jeton($candidat))
            ->postJson('/api/interests', [
                'job_offer_id' => $offre->id,
                'candidate_profile_id' => $this->profilDe($autre)->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.interest.candidate_profile_id', $this->profilDe($candidat)->id);

        $this->assertSame(0, OfferInterest::where('candidate_profile_id', $this->profilDe($autre)->id)->count());
    }

    /**
     * REJEU. Un client mobile renvoie sa requete des que le reseau bronche.
     * Cote employeur, le deck SORT la carte des qu'une decision est posee :
     * consulter l'eligibilite sur le rejeu repondait « ce candidat n'est plus
     * disponible » a un geste qui avait pourtant abouti, et faisait
     * disparaitre la carte de l'ecran. record() est idempotent, la garde ne
     * doit pas l'en empecher.
     */
    public function test_an_employer_replaying_its_like_is_not_refused(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        $premier = $this->service->like($employeur, $offre, $this->profilDe($candidat));
        $rejoue = $this->service->like($employeur, $offre, $this->profilDe($candidat));

        $this->assertSame($premier['interest']['id'], $rejoue['interest']['id']);
        $this->assertSame(InterestDecision::LIKE, $rejoue['interest']['decision']);
        $this->assertSame(1, OfferInterest::count());
        // Une seule notification : le rejeu ne repropose rien au candidat.
        $this->assertSame(
            1,
            Notification::where('user_id', $candidat->id)
                ->where('type', NotificationType::INTEREST_RECEIVED)->count(),
        );
    }

    // Meme raison cote candidat : le quota ne doit pas transformer le rejeu
    // du vingtieme geste en 429 pour un geste deja compte.
    public function test_a_candidate_at_quota_can_replay_its_last_like(): void
    {
        $candidat = $this->candidat();
        $profil = $this->profilDe($candidat);
        $employeur = $this->employeur();

        JobOffer::factory()->count(30)->published()->located()
            ->create(['company_id' => $employeur->company->id]);

        $cible = $this->offrePubliee($employeur);
        $this->service->like($candidat, $cible);

        // 19 autres LIKE dans la journee : le quota de 20 est atteint.
        for ($i = 0; $i < 19; $i++) {
            OfferInterest::factory()->candidateLiked()->create([
                'candidate_profile_id' => $profil->id,
                'job_offer_id' => JobOffer::factory()->published()->located()
                    ->create(['company_id' => $employeur->company->id])->id,
            ]);
        }

        $rejoue = $this->service->like($candidat, $cible);
        $this->assertSame(InterestDecision::LIKE, $rejoue['interest']['decision']);

        // Mais un geste NEUF reste refuse.
        $this->assertCode('INTEREST_QUOTA_REACHED', fn () => $this->service->like(
            $candidat,
            $this->offrePubliee($employeur),
        ));
    }

    /**
     * Un brouillon n'a pas de page publique : la notification « X s'interesse
     * a ton profil » pointerait vers /offres/{id}, que
     * PublicJobOfferController refuse. L'employeur publie d'abord.
     */
    public function test_employer_like_requires_a_published_offer(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $brouillon = JobOffer::factory()->located()->create([
            'company_id' => $employeur->company->id,
            'status' => JobOfferStatus::DRAFT,
        ]);

        $this->assertCode(
            'JOB_OFFER_NOT_PUBLISHED',
            fn () => $this->service->like($employeur, $brouillon, $this->profilDe($candidat)),
        );
        $this->assertSame(0, OfferInterest::count());
        $this->assertSame(0, Notification::where('user_id', $candidat->id)->count());
    }

    // Une ligne fermee (offre archivee, compte supprime...) n'accepte plus de
    // geste : la rouvrir par un LIKE ferait renaitre un match que l'autre
    // partie a deja vu disparaitre.
    public function test_a_closed_interest_refuses_a_new_gesture(): void
    {
        $candidat = $this->candidat();
        $profil = $this->profilDe($candidat);
        $offre = $this->offrePubliee($this->employeur());

        OfferInterest::factory()->closed()
            ->create(['candidate_profile_id' => $profil->id, 'job_offer_id' => $offre->id]);

        $this->assertCode('INTEREST_CLOSED', fn () => $this->service->like($candidat, $offre));
    }

    // Meme garde que pour « Ca m'interesse », sur le lot : le corps porte le
    // profil d'un autre, le service ne doit poser les PASS que sur celui de
    // l'appelant.
    public function test_pass_batch_ignores_a_candidate_profile_id_from_the_body(): void
    {
        $candidat = $this->candidat([], ['email' => 'moi@example.test']);
        $autre = $this->candidat([], ['email' => 'autre@example.test']);
        $offre = $this->offrePubliee($this->employeur());

        $this->withToken($this->jeton($candidat))
            ->postJson('/api/interests/batch', [
                'job_offer_ids' => [$offre->id],
                'candidate_profile_ids' => [$this->profilDe($autre)->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.passed', 1);

        $this->assertSame(0, OfferInterest::where('candidate_profile_id', $this->profilDe($autre)->id)->count());
        $this->assertSame(
            InterestDecision::PASS,
            OfferInterest::where('candidate_profile_id', $this->profilDe($candidat)->id)->value('candidate_decision'),
        );
    }

    private function assertCode(string $code, callable $appel): void
    {
        try {
            $appel();
            $this->fail("Attendu : {$code}.");
        } catch (ApiException $e) {
            $this->assertSame($code, $e->errorCode);
        }
    }
}
