<?php

namespace Tests\Feature;

use App\Enums\MatchClosedReason;
use App\Enums\NotificationType;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\JobOffer;
use App\Models\Notification;
use App\Models\OfferInterest;
use App\Services\MatchClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fermeture des interets et des matchs (contrat lot 1 §2.5).
 *
 * Ce que ce fichier garde : quand une offre ou un compte disparait, la
 * personne d'en face l'APPREND, au lieu de voir sa carte s'evanouir. C'est
 * la contrepartie directe de la promesse « reponse garantie » — un match qui
 * se ferme en silence est indiscernable d'un employeur qui ne repond pas.
 *
 * Et la regle inverse, tout aussi importante : un interet a sens unique ne
 * notifie personne. Le prevenir reviendrait a reveler apres coup un geste
 * que le produit n'avait jamais montre.
 */
class MatchClosingServiceTest extends TestCase
{
    use RefreshDatabase;

    private MatchClosingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(MatchClosingService::class);
    }

    private function offrePubliee(): JobOffer
    {
        return JobOffer::factory()->published()->create([
            'company_id' => Company::factory()->verified(),
        ]);
    }

    public function test_closes_open_interests_of_an_offer(): void
    {
        $offre = $this->offrePubliee();

        $matche = OfferInterest::factory()->matched()->create(['job_offer_id' => $offre->id]);
        $simple = OfferInterest::factory()->candidateLiked()->create(['job_offer_id' => $offre->id]);
        // Sur une autre offre : ne doit pas bouger.
        $ailleurs = OfferInterest::factory()->matched()->create();

        $ferme = $this->service->closeForOffer($offre, MatchClosedReason::OFFER_ARCHIVED);

        $this->assertSame(2, $ferme);

        foreach ([$matche, $simple] as $ligne) {
            $ligne->refresh();
            $this->assertNotNull($ligne->closed_at);
            $this->assertSame(MatchClosedReason::OFFER_ARCHIVED, $ligne->closed_reason);
        }

        $this->assertNull($ailleurs->fresh()->closed_at, "l'interet d'une autre offre n'est pas concerne");
    }

    public function test_notifies_the_other_party_only_when_matched(): void
    {
        $offre = $this->offrePubliee();

        $matche = OfferInterest::factory()->matched()->create(['job_offer_id' => $offre->id]);
        $simple = OfferInterest::factory()->candidateLiked()->create(['job_offer_id' => $offre->id]);

        $this->service->closeForOffer($offre, MatchClosedReason::OFFER_EXPIRED);

        $this->assertSame(1, Notification::where('type', NotificationType::MATCH_CLOSED)->count());

        $notification = Notification::where('type', NotificationType::MATCH_CLOSED)->firstOrFail();
        $this->assertSame($matche->candidateProfile->user_id, $notification->user_id);
        $this->assertSame('/mes-candidatures', $notification->link);
        $this->assertStringContainsString('échéance', $notification->message, 'la raison se lit dans le message');

        $this->assertSame(0, Notification::where('user_id', $simple->candidateProfile->user_id)
            ->where('type', NotificationType::MATCH_CLOSED)
            ->count());
    }

    /**
     * Le destinataire depend du POINT D'ENTREE, jamais de la raison :
     * ACCOUNT_DELETED arrive par les deux chemins (un candidat qui part, une
     * entreprise qui part). Une version anterieure choisissait sur la raison
     * et prevenait le mauvais cote dans un cas sur deux.
     */
    public function test_the_recipient_follows_the_entry_point_not_the_reason(): void
    {
        $offre = $this->offrePubliee();
        $employeur = $offre->company->user;
        $interet = OfferInterest::factory()->matched()->create(['job_offer_id' => $offre->id]);
        $candidat = $interet->candidateProfile;

        // Meme raison, cote profil : c'est l'employeur qui apprend.
        $this->service->closeForCandidateProfile($candidat, MatchClosedReason::ACCOUNT_DELETED);

        $notification = Notification::where('type', NotificationType::MATCH_CLOSED)->firstOrFail();
        $this->assertSame($employeur->id, $notification->user_id);
        $this->assertSame('/mes-offres', $notification->link);

        // Le nom complet ne passe pas dans un message in-app : meme regle
        // d'exposition que la carte candidat.
        $this->assertStringContainsString($candidat->first_name, $notification->message);
        $this->assertStringNotContainsString($candidat->last_name, $notification->message);
    }

    /**
     * La ligne se retrouve par le couple (profil, offre) et non par
     * application_id : celui-ci n'est renseigne que si le match existait
     * deja au moment du dossier. Chercher par l'identifiant de candidature
     * laisserait ouvertes exactement les lignes qu'un retrait doit fermer.
     */
    public function test_close_for_application_finds_the_row_without_application_id(): void
    {
        $offre = $this->offrePubliee();
        $employeur = $offre->company->user;
        $profil = CandidateProfile::factory()->adult()->create();

        $interet = OfferInterest::factory()->matched()->create([
            'candidate_profile_id' => $profil->id,
            'job_offer_id' => $offre->id,
        ]);
        $this->assertNull($interet->application_id);

        $candidature = Application::create([
            'candidate_profile_id' => $profil->id,
            'job_offer_id' => $offre->id,
        ]);

        $this->assertSame(1, $this->service->closeForApplication($candidature, MatchClosedReason::APPLICATION_WITHDRAWN));

        $this->assertSame(MatchClosedReason::APPLICATION_WITHDRAWN, $interet->fresh()->closed_reason);
        $this->assertSame(1, Notification::where('user_id', $employeur->id)
            ->where('type', NotificationType::MATCH_CLOSED)
            ->count());
    }

    /**
     * ExpireJobOffers repasse chaque nuit sur les memes offres : une seconde
     * passe ne doit ni recompter, ni renotifier.
     */
    public function test_is_idempotent(): void
    {
        $offre = $this->offrePubliee();
        OfferInterest::factory()->matched()->create(['job_offer_id' => $offre->id]);

        $this->assertSame(1, $this->service->closeForOffer($offre, MatchClosedReason::OFFER_EXPIRED));
        $this->assertSame(0, $this->service->closeForOffer($offre, MatchClosedReason::OFFER_EXPIRED));

        $this->assertSame(1, Notification::where('type', NotificationType::MATCH_CLOSED)->count());
    }

    // Une offre sans interet ne doit pas faire echouer l'archivage.
    public function test_an_offer_without_interests_closes_nothing(): void
    {
        $this->assertSame(0, $this->service->closeForOffer($this->offrePubliee(), MatchClosedReason::OFFER_DELETED));
        $this->assertSame(0, Notification::count());
    }
}
