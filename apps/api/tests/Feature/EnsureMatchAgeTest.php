<?php

namespace Tests\Feature;

use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AideMatch;
use Tests\TestCase;

/**
 * Seize ans sur toute route du match, quel que soit le client (le site reste
 * a quinze ans pour tout le reste).
 *
 * La mesure de production du 2026-09-22 compte 3 candidats de moins de 16 ans
 * parmi 115, et 8 sans date de naissance exploitable : ces comptes existent
 * deja, la garde doit donc repondre proprement plutot que planter.
 */
class EnsureMatchAgeTest extends TestCase
{
    use AideMatch;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chargerLesRoutesDuMatch();
        $this->ouvrirLePerimetre();
    }

    public function test_no_profile_403(): void
    {
        $user = User::factory()->candidate()->create();

        $this->withToken($this->jeton($user))
            ->getJson('/api/discover/offers')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'CANDIDATE_PROFILE_REQUIRED');
    }

    public function test_no_birth_date_403(): void
    {
        $user = User::factory()->candidate()->create();
        CandidateProfile::factory()->create(['user_id' => $user->id, 'birth_date' => null]);

        $this->withToken($this->jeton($user))
            ->getJson('/api/discover/offers')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'BIRTH_DATE_REQUIRED');
    }

    public function test_15_years_old_403(): void
    {
        $user = User::factory()->candidate()->create();
        CandidateProfile::factory()->minor15()->create(['user_id' => $user->id]);

        $this->withToken($this->jeton($user))
            ->getJson('/api/discover/offers')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'MATCH_MIN_AGE');
    }

    public function test_16_years_old_passes(): void
    {
        $user = User::factory()->candidate()->create();
        CandidateProfile::factory()->aged(16)->located()->create(['user_id' => $user->id]);

        $this->withToken($this->jeton($user))
            ->getJson('/api/discover/offers')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_the_guard_covers_interests_and_matches_too(): void
    {
        $user = User::factory()->candidate()->create();
        CandidateProfile::factory()->minor15()->create(['user_id' => $user->id]);
        $jeton = $this->jeton($user);

        $this->withToken($jeton)->getJson('/api/matches')
            ->assertStatus(403)->assertJsonPath('error.code', 'MATCH_MIN_AGE');
        $this->withToken($jeton)->deleteJson('/api/interests/last')
            ->assertStatus(403)->assertJsonPath('error.code', 'MATCH_MIN_AGE');
        $this->withToken($jeton)->getJson('/api/external-interests')
            ->assertStatus(403)->assertJsonPath('error.code', 'MATCH_MIN_AGE');
    }

    public function test_employer_is_not_checked(): void
    {
        // Un employeur n'a pas de date de naissance : la garde le laisse
        // passer, ses propres gardes vivent dans les services.
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        $this->withToken($this->jeton($employeur))
            ->getJson('/api/discover/candidates?job_offer_id='.$offre->id)
            ->assertOk();
    }
}
