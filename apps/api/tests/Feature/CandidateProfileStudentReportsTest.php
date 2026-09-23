<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Retours d'etudiants reels remontes par Pierre le 2026-09-23 :
 * impossible de saisir plus de cinq experiences, et l'onglet competences
 * n'enregistre plus. Ces tests rejouent les deux gestes par les routes HTTP,
 * comme le fait le navigateur, pour savoir si le defaut est dans le code
 * metier ou ailleurs (donnees, serveur, interface).
 */
class CandidateProfileStudentReportsTest extends TestCase
{
    use RefreshDatabase;

    private function candidateWithProfile(): User
    {
        $user = User::factory()->create(['role' => UserRole::CANDIDATE]);
        $this->actingAs($user, 'api')->postJson('/api/candidate-profile', [
            'first_name' => 'Lea',
            'last_name' => 'Girard',
            'birth_date' => '2006-04-12',
        ])->assertCreated();

        // Recharge : l'instance $user garde en cache une relation
        // candidateProfile lue AVANT la creation (donc nulle), et actingAs
        // reutilise cette meme instance a la requete suivante.
        return $user->fresh();
    }

    public function test_a_candidate_can_add_more_than_five_experiences(): void
    {
        $user = $this->candidateWithProfile();

        for ($i = 1; $i <= 8; $i++) {
            $this->actingAs($user, 'api')
                ->postJson('/api/candidate-profile/experiences', [
                    'title' => "Poste {$i}",
                    'company' => "Entreprise {$i}",
                    'start_date' => '2025-0'.min(9, $i).'-01',
                ])
                ->assertCreated("L'experience n° {$i} a ete refusee.");
        }

        $profile = $this->actingAs($user, 'api')->getJson('/api/candidate-profile');

        $profile->assertOk();
        $this->assertCount(8, $profile->json('data.experiences'));
    }

    public function test_skills_are_saved_and_returned_by_the_profile(): void
    {
        $user = $this->candidateWithProfile();

        $this->actingAs($user, 'api')
            ->putJson('/api/candidate-profile/skills', ['names' => ['Vente']])
            ->assertOk();

        // Deuxieme envoi : l'interface renvoie la liste complete a chaque
        // ajout, c'est ainsi qu'une competence s'ajoute a une autre.
        $this->actingAs($user, 'api')
            ->putJson('/api/candidate-profile/skills', ['names' => ['Vente', 'Relation client']])
            ->assertOk();

        $profile = $this->actingAs($user, 'api')->getJson('/api/candidate-profile');

        $this->assertSame(
            ['Relation client', 'Vente'],
            collect($profile->json('data.skills'))->pluck('name')->sort()->values()->all(),
        );
    }

    // Une competence deja connue d'un autre candidat : Skill::firstOrCreate
    // doit la retrouver, pas tenter de la recreer.
    public function test_a_skill_already_used_by_someone_else_is_reused(): void
    {
        $premier = $this->candidateWithProfile();
        $this->actingAs($premier, 'api')
            ->putJson('/api/candidate-profile/skills', ['names' => ['Vente']])
            ->assertOk();

        $second = $this->candidateWithProfile();
        $this->actingAs($second, 'api')
            ->putJson('/api/candidate-profile/skills', ['names' => ['Vente']])
            ->assertOk();

        $this->assertSame(1, Skill::where('name', 'Vente')->count());
    }
}
