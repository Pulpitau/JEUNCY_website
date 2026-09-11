<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\CandidateProfile;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CvthequeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * L'age du candidat, visible des recruteurs.
 *
 * Le cout d'un alternant depend de sa tranche d'age : c'est un critere de
 * selection a part entiere pour une entreprise. Ces tests verifient que l'AGE
 * est expose — et que la date de naissance, elle, ne l'est jamais. Exposer le
 * premier permet de garder la seconde privee ; les deux ne se negocient pas
 * l'un contre l'autre.
 */
class CandidateAgeTest extends TestCase
{
    use RefreshDatabase;

    private CvthequeService $cvtheque;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cvtheque = $this->app->make(CvthequeService::class);
        // Date fixe : un age calcule ne doit pas faire echouer le test le jour
        // d'un anniversaire.
        Carbon::setTestNow('2026-09-11 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function abonne(): User
    {
        $user = User::create(['email' => 'rh@nexatech.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
        Subscription::create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::ACTIVE,
            'amount_cents' => 49900,
            'stripe_subscription_id' => 'sub_'.$user->id,
            'stripe_customer_id' => 'cus_'.$user->id,
        ]);

        return $user;
    }

    private function candidat(?string $naissance, string $prenom = 'Lea'): CandidateProfile
    {
        static $n = 0;
        $n++;
        $user = User::create(['email' => "candidat{$n}@example.com", 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);

        return CandidateProfile::create([
            'user_id' => $user->id,
            'first_name' => $prenom,
            'last_name' => 'Girard',
            'city' => 'Perpignan',
            'birth_date' => $naissance,
        ]);
    }

    // ------------------------------------------------------------------
    // Ce que voit le recruteur
    // ------------------------------------------------------------------

    public function test_the_list_shows_the_age_but_never_the_birth_date(): void
    {
        $this->candidat('2004-05-01');

        $payload = $this->cvtheque->search($this->abonne(), [])->items()[0]->toArray();

        $this->assertSame(22, $payload['age']);
        $this->assertArrayNotHasKey('birth_date', $payload, 'La date de naissance ne doit jamais sortir de la liste.');
    }

    public function test_the_detail_shows_the_age_but_never_the_birth_date(): void
    {
        $profil = $this->candidat('2008-09-11');

        $payload = $this->cvtheque->find($this->abonne(), $profil->id)->toArray();

        // Anniversaire le jour du test : 18 ans revolus.
        $this->assertSame(18, $payload['age']);
        $this->assertArrayNotHasKey('birth_date', $payload, 'La fiche porte l\'age, pas la date.');
    }

    // Un profil sans date de naissance (anciens comptes) reste visible : on
    // affiche simplement rien, on ne le cache pas.
    public function test_a_profile_without_birth_date_is_still_listed_with_a_null_age(): void
    {
        $this->candidat(null);

        $payload = $this->cvtheque->search($this->abonne(), [])->items()[0]->toArray();

        $this->assertArrayHasKey('age', $payload);
        $this->assertNull($payload['age']);
    }

    // ------------------------------------------------------------------
    // Le filtre par age
    // ------------------------------------------------------------------

    public function test_recruiters_can_filter_by_age_range(): void
    {
        $this->candidat('2010-01-01', 'Mineur');      // 16 ans
        $this->candidat('2004-05-01', 'Vingt');       // 22 ans
        $this->candidat('1998-03-15', 'Vingt-huit');  // 28 ans
        $this->candidat(null, 'Inconnu');

        $abonne = $this->abonne();
        $prenoms = fn (array $filtres) => collect($this->cvtheque->search($abonne, $filtres)->items())
            ->pluck('first_name')->sort()->values()->all();

        $this->assertSame(['Vingt', 'Vingt-huit'], $prenoms(['age_min' => 18]));
        $this->assertSame(['Mineur', 'Vingt'], $prenoms(['age_max' => 25]));
        $this->assertSame(['Vingt'], $prenoms(['age_min' => 18, 'age_max' => 25]));
    }

    // « 25 ans au plus » inclut celui qui a 25 ans revolus et exclut celui qui
    // vient d'en avoir 26 : la borne est inclusive, comme un recruteur la lit.
    public function test_the_upper_bound_is_inclusive(): void
    {
        $this->candidat('2001-09-11', 'Vingt-cinq');  // 25 ans aujourd'hui
        $this->candidat('2000-09-11', 'Vingt-six');   // 26 ans aujourd'hui

        $prenoms = collect($this->cvtheque->search($this->abonne(), ['age_max' => 25])->items())
            ->pluck('first_name')->all();

        $this->assertSame(['Vingt-cinq'], $prenoms);
    }

    // Un profil sans date ne peut pas etre affirme dans une tranche : des
    // qu'un filtre d'age est pose, il sort des resultats.
    public function test_a_profile_without_birth_date_is_excluded_by_an_age_filter(): void
    {
        $this->candidat(null, 'Inconnu');
        $this->candidat('2004-05-01', 'Vingt');

        $prenoms = collect($this->cvtheque->search($this->abonne(), ['age_min' => 15])->items())
            ->pluck('first_name')->all();

        $this->assertSame(['Vingt'], $prenoms);
    }

    // ------------------------------------------------------------------
    // La saisie, cote candidat (par HTTP : c'est la validation qui compte)
    // ------------------------------------------------------------------

    private function candidatSansProfil(): User
    {
        return User::create(['email' => 'nouveau@example.com', 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);
    }

    public function test_creating_a_profile_requires_a_birth_date(): void
    {
        $this->actingAs($this->candidatSansProfil(), 'api')
            ->postJson('/api/candidate-profile', ['first_name' => 'Lea', 'last_name' => 'Girard'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_INPUT');
    }

    public function test_a_candidate_under_15_is_refused(): void
    {
        $this->actingAs($this->candidatSansProfil(), 'api')
            ->postJson('/api/candidate-profile', [
                'first_name' => 'Lea', 'last_name' => 'Girard',
                'birth_date' => '2012-01-01', // 14 ans
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_INPUT');
    }

    public function test_a_candidate_of_15_is_accepted(): void
    {
        $this->actingAs($this->candidatSansProfil(), 'api')
            ->postJson('/api/candidate-profile', [
                'first_name' => 'Lea', 'last_name' => 'Girard',
                'birth_date' => '2011-09-11', // 15 ans aujourd'hui
            ])
            ->assertStatus(201);

        $this->assertSame(15, CandidateProfile::firstOrFail()->age);
    }

    // Une mise a jour partielle (la ville, par exemple) ne doit pas exiger de
    // renvoyer la date ; mais on ne peut plus l'effacer.
    public function test_updating_other_fields_does_not_require_resending_the_birth_date(): void
    {
        $user = $this->candidatSansProfil();
        CandidateProfile::create(['user_id' => $user->id, 'first_name' => 'Lea', 'last_name' => 'Girard', 'birth_date' => '2004-05-01']);

        $this->actingAs($user, 'api')
            ->patchJson('/api/candidate-profile', ['city' => 'Toulouse'])
            ->assertOk();

        $this->actingAs($user, 'api')
            ->patchJson('/api/candidate-profile', ['birth_date' => null])
            ->assertStatus(400);
    }
}
