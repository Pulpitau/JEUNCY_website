<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\InterestDecision;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use App\Services\DiscoverService;
use App\Services\InterestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AideMatch;
use Tests\TestCase;

/**
 * Annulation du dernier geste.
 *
 * DECISION DU LOT, ecrite aussi dans InterestService et dans MOBILE.md §5 :
 * sans worker, la notification et l'email partent dans l'appel qui cree le
 * match et *_notified_at sont poses dans la foulee. Un match n'est donc
 * jamais annulable en pratique. La fenetre de 60 s reste codee pour le jour
 * ou une file d'attente differera les envois ; test_match_is_not_undoable_once_notified
 * verifie l'etat d'aujourd'hui, test_a_silent_match_within_60s_is_undoable
 * verifie que la mecanique prevue fonctionne.
 */
class UndoLastInterestTest extends TestCase
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

    public function test_nothing_to_undo_is_404(): void
    {
        $candidat = $this->candidat();

        $this->withToken($this->jeton($candidat))
            ->deleteJson('/api/interests/last')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOTHING_TO_UNDO');
    }

    public function test_undo_within_5_minutes(): void
    {
        $candidat = $this->candidat();
        $offre = $this->offrePubliee($this->employeur());

        $this->service->like($candidat, $offre);

        $reponse = $this->withToken($this->jeton($candidat))->deleteJson('/api/interests/last');

        $reponse->assertOk()->assertJsonPath('data.undone.job_offer_id', $offre->id);
        // La ligne etait vide de tout le reste : elle disparait plutot que
        // de rester bloquer le couple par la contrainte d'unicite.
        $this->assertSame(0, OfferInterest::count());
    }

    public function test_undo_after_5_minutes_409(): void
    {
        $candidat = $this->candidat();
        $profil = $this->profilDe($candidat);
        $offre = $this->offrePubliee($this->employeur());

        OfferInterest::factory()->candidateLiked()->create([
            'candidate_profile_id' => $profil->id,
            'job_offer_id' => $offre->id,
            'candidate_decided_at' => now()->subMinutes(6),
        ]);

        $this->withToken($this->jeton($candidat))
            ->deleteJson('/api/interests/last')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'UNDO_WINDOW_EXPIRED');
    }

    public function test_match_is_not_undoable_once_notified(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        $this->service->like($employeur, $offre, $this->profilDe($candidat));
        $this->service->like($candidat, $offre);

        $this->withToken($this->jeton($candidat))
            ->deleteJson('/api/interests/last')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'MATCH_ALREADY_NOTIFIED');
    }

    public function test_a_silent_match_within_60s_is_undoable(): void
    {
        $candidat = $this->candidat();
        $profil = $this->profilDe($candidat);
        $offre = $this->offrePubliee($this->employeur());

        // Ce que produirait une file d'attente : match pose, personne encore
        // prevenu.
        OfferInterest::factory()->candidateLiked()->employerLiked()->create([
            'candidate_profile_id' => $profil->id,
            'job_offer_id' => $offre->id,
            'matched_at' => now()->subSeconds(10),
        ]);

        $this->withToken($this->jeton($candidat))
            ->deleteJson('/api/interests/last')
            ->assertOk();

        $ligne = OfferInterest::firstOrFail();
        $this->assertNull($ligne->matched_at);
        $this->assertNull($ligne->candidate_decision);
        $this->assertSame(InterestDecision::LIKE, $ligne->employer_decision);
    }

    public function test_an_attached_application_blocks_the_undo(): void
    {
        $candidat = $this->candidat();
        $profil = $this->profilDe($candidat);
        $offre = $this->offrePubliee($this->employeur());

        $candidature = $profil->applications()->create([
            'job_offer_id' => $offre->id,
            'status' => ApplicationStatus::SENT,
        ]);
        OfferInterest::factory()->candidateLiked()->create([
            'candidate_profile_id' => $profil->id,
            'job_offer_id' => $offre->id,
            'application_id' => $candidature->id,
        ]);

        $this->withToken($this->jeton($candidat))
            ->deleteJson('/api/interests/last')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'APPLICATION_ATTACHED');
    }

    public function test_undo_frees_quota(): void
    {
        $candidat = $this->candidat();
        $profil = $this->profilDe($candidat);
        $employeur = $this->employeur();
        JobOffer::factory()->count(30)->published()->located()
            ->create(['company_id' => $employeur->company->id]);

        $offre = $this->offrePubliee($employeur);
        $this->service->like($candidat, $offre);

        $quota = $this->app->make(DiscoverService::class);
        $this->assertSame(1, $quota->quotaFor($profil)['used']);

        $this->service->undoLast($candidat);

        $this->assertSame(0, $quota->quotaFor($profil->fresh())['used']);
    }

    public function test_employer_undoes_its_own_last_gesture(): void
    {
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);
        $candidat = $this->candidat();

        $this->service->like($employeur, $offre, $this->profilDe($candidat));

        $this->withToken($this->jeton($employeur))
            ->deleteJson('/api/interests/last')
            ->assertOk()
            ->assertJsonPath('data.undone.candidate_profile_id', $this->profilDe($candidat)->id);

        $this->assertSame(0, OfferInterest::count());
    }

    public function test_an_employer_never_undoes_a_gesture_on_another_offer(): void
    {
        $employeur = $this->employeur();
        $autre = $this->employeur();
        $offreDeLAutre = $this->offrePubliee($autre);
        $candidat = $this->candidat();

        $this->service->like($autre, $offreDeLAutre, $this->profilDe($candidat));

        $this->withToken($this->jeton($employeur))
            ->deleteJson('/api/interests/last')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOTHING_TO_UNDO');
    }
}
