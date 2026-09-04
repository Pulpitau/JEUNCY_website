<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\CandidateProfile;
use App\Models\User;
use App\Services\AdminService;
use App\Services\CvthequeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Role STAFF : membre de l'equipe Jeuncy qui remplit la CVtheque.
//
// Ce qui compte ici est autant ce qu'il PEUT que ce qu'il ne peut PAS. Le
// patron a refuse de lui donner l'administration, et cette limite doit tenir
// dans le code, pas seulement dans l'interface.
class StaffRoleTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(UserRole $role, string $email): User
    {
        return User::create([
            'email' => $email,
            'password_hash' => 'x',
            'role' => $role,
        ]);
    }

    private function makeCandidate(array $overrides = []): CandidateProfile
    {
        static $n = 0;
        $n++;

        return CandidateProfile::create(array_merge([
            'user_id' => $this->makeUser(UserRole::CANDIDATE, "candidat{$n}@example.com")->id,
            'first_name' => 'Lea',
            'last_name' => 'Girard',
            'is_visible_in_cvtheque' => true,
        ], $overrides));
    }

    // --- Ce qu'il peut ---

    public function test_staff_reads_the_cvtheque_without_a_subscription(): void
    {
        $this->makeCandidate();
        $staff = $this->makeUser(UserRole::STAFF, 'collegue@jeuncy.com');

        $page = $this->app->make(CvthequeService::class)->search($staff, []);

        $this->assertCount(1, $page->items());
    }

    // Le besoin exact du collegue : retrouver quelqu'un qu'il vient d'avoir au
    // telephone. La recherche libre ne couvrait ni le prenom ni le nom — meme
    // un admin ne pouvait pas retrouver un candidat par son nom.
    public function test_the_search_finds_a_candidate_by_name(): void
    {
        $this->makeCandidate(['first_name' => 'Rostom', 'last_name' => 'Ghazli']);
        $this->makeCandidate(['first_name' => 'Louis', 'last_name' => 'Mouche']);
        $staff = $this->makeUser(UserRole::STAFF, 'collegue@jeuncy.com');

        $service = $this->app->make(CvthequeService::class);

        $this->assertCount(1, $service->search($staff, ['q' => 'Ghazli'])->items());
        $this->assertCount(1, $service->search($staff, ['q' => 'Rostom'])->items());
    }

    // Un profil retire de la CVtheque par son titulaire reste invisible, y
    // compris pour l'equipe : le choix du candidat prime.
    public function test_a_hidden_profile_stays_hidden_for_staff(): void
    {
        $this->makeCandidate(['is_visible_in_cvtheque' => false]);
        $staff = $this->makeUser(UserRole::STAFF, 'collegue@jeuncy.com');

        $this->assertCount(0, $this->app->make(CvthequeService::class)->search($staff, [])->items());
    }

    // --- Ce qu'il ne peut PAS ---

    public function test_staff_is_refused_by_the_admin_routes(): void
    {
        $staff = $this->makeUser(UserRole::STAFF, 'collegue@jeuncy.com');

        foreach (['/api/admin/stats', '/api/admin/users', '/api/admin/payments'] as $route) {
            $this->actingAs($staff, 'api')->getJson($route)->assertStatus(403);
        }
    }

    public function test_staff_cannot_suspend_an_account(): void
    {
        $staff = $this->makeUser(UserRole::STAFF, 'collegue@jeuncy.com');
        $cible = $this->makeUser(UserRole::CANDIDATE, 'cible@example.com');

        $this->actingAs($staff, 'api')
            ->postJson("/api/admin/users/{$cible->id}/suspend")
            ->assertStatus(403);

        $this->assertFalse($cible->fresh()->is_suspended);
    }

    // --- L'attribution du role ---

    public function test_an_admin_promotes_and_demotes_a_candidate(): void
    {
        $service = $this->app->make(AdminService::class);
        $admin = $this->makeUser(UserRole::ADMIN, 'admin@jeuncy.com');
        $cible = $this->makeUser(UserRole::CANDIDATE, 'collegue@jeuncy.com');

        $this->assertSame(UserRole::STAFF, $service->setStaffRole($admin, $cible, true)->role);
        $this->assertSame(UserRole::CANDIDATE, $service->setStaffRole($admin, $cible->fresh(), false)->role);
    }

    // Une entreprise porte un profil, des offres et des paiements : changer son
    // role les detacherait de leur titulaire.
    public function test_a_company_account_cannot_be_switched(): void
    {
        $service = $this->app->make(AdminService::class);
        $admin = $this->makeUser(UserRole::ADMIN, 'admin@jeuncy.com');
        $entreprise = $this->makeUser(UserRole::COMPANY, 'rh@nexatech.example.com');

        $this->expectException(ApiException::class);
        $service->setStaffRole($admin, $entreprise, true);
    }

    // Meme garde que la suspension : un admin ne se retire pas ses propres
    // droits par megarde.
    public function test_an_admin_cannot_change_their_own_role(): void
    {
        $service = $this->app->make(AdminService::class);
        $admin = $this->makeUser(UserRole::ADMIN, 'admin@jeuncy.com');

        $this->expectException(ApiException::class);
        $service->setStaffRole($admin, $admin, true);
    }
}
