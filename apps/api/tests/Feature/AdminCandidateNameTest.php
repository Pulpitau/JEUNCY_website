<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CandidateProfile;
use App\Models\Skill;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Correction des noms de candidats mal lus par l'import de CV.
//
// L'import cree le profil sans que le candidat relise l'identite tiree de son
// PDF : un nom rate ne se voit qu'apres coup, dans la CVtheque. Deux profils
// reels ont ete crees au nom de "Permis B" et d'une suite de competences.
class AdminCandidateNameTest extends TestCase
{
    use RefreshDatabase;

    private AdminService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(AdminService::class);
    }

    private function makeProfile(string $first, string $last): CandidateProfile
    {
        static $n = 0;
        $n++;

        $user = User::create([
            'email' => "candidat{$n}@example.com",
            'password_hash' => 'x',
            'role' => UserRole::CANDIDATE,
        ]);

        return CandidateProfile::create([
            'user_id' => $user->id,
            'first_name' => $first,
            'last_name' => $last,
        ]);
    }

    // --- Detection ---

    /**
     * Les deux cas reels, plus les formes voisines.
     */
    public function test_it_flags_names_the_import_should_never_have_produced(): void
    {
        // "Prospection" et "Encaissement" ne se distinguent d'un vrai nom par
        // aucun critere de forme. C'est leur presence dans le referentiel de
        // competences de la plateforme qui les trahit.
        Skill::create(['name' => 'Prospection']);
        Skill::create(['name' => 'Encaissement']);

        $cas = [
            ['Permis', 'B'],
            ['Prospection', 'Encaissement'],
            ['Curriculum', 'Vitae'],
            ['Alternance', 'Vente'],
            ['Je', 'Recherche Une Alternance Commerciale'],
            ['Telephone', '0612345678'],
            ['Lea', ''],
        ];

        foreach ($cas as [$first, $last]) {
            $this->assertTrue(
                $this->service->nameLooksImplausible($first, $last),
                "\"{$first} {$last}\" devrait etre signale.",
            );
        }
    }

    // Un nom inhabituel reste un nom : signaler trop large ferait perdre
    // confiance a l'outil et noierait les vrais cas.
    public function test_it_leaves_real_names_alone(): void
    {
        $cas = [
            ['Lea', 'Girard'],
            ['Inaya', 'Ben Abdeslem'],
            ['Jean-Baptiste', 'Moreau'],
            ['Rostom', 'Ghazli'],
            ['Marie', "O'Brien"],
            ['Anne', 'de la Fontaine'],
        ];

        foreach ($cas as [$first, $last]) {
            $this->assertFalse(
                $this->service->nameLooksImplausible($first, $last),
                "\"{$first} {$last}\" ne devrait pas etre signale.",
            );
        }
    }

    // --- Liste ---

    public function test_the_suspicious_filter_returns_only_flagged_profiles(): void
    {
        $this->makeProfile('Lea', 'Girard');
        $abime = $this->makeProfile('Permis', 'B');
        $this->makeProfile('Rostom', 'Ghazli');

        $page = $this->service->listCandidateProfiles(['suspicious' => true]);

        $this->assertCount(1, $page->items());
        $this->assertSame($abime->id, $page->items()[0]->id);
    }

    public function test_without_the_filter_every_profile_is_listed(): void
    {
        $this->makeProfile('Lea', 'Girard');
        $this->makeProfile('Permis', 'B');

        $this->assertCount(2, $this->service->listCandidateProfiles([])->items());
    }

    // Un compte supprime par son titulaire est anonymise, pas efface (les
    // pieces comptables doivent etre conservees). Ce vestige n'est plus une
    // personne : le faire apparaitre dans une liste de correction laisserait
    // croire qu'il reste quelque chose a corriger.
    public function test_a_deleted_account_is_never_listed(): void
    {
        $profile = $this->makeProfile('Permis', 'B');
        $profile->user->update(['deleted_account_at' => now()]);

        $this->assertCount(0, $this->service->listCandidateProfiles(['suspicious' => true])->items());
        $this->assertCount(0, $this->service->listCandidateProfiles([])->items());
    }

    // --- Correction ---

    public function test_it_corrects_the_name(): void
    {
        $profile = $this->makeProfile('Permis', 'B');

        $corrige = $this->service->updateCandidateName($profile, 'Rostom', 'Ghazli');

        $this->assertSame('Rostom', $corrige->first_name);
        $this->assertSame('Ghazli', $corrige->last_name);
        $this->assertSame('Rostom', $profile->fresh()->first_name);
    }

    public function test_it_trims_the_corrected_name(): void
    {
        $profile = $this->makeProfile('Permis', 'B');

        $corrige = $this->service->updateCandidateName($profile, '  Rostom ', ' Ghazli  ');

        $this->assertSame('Rostom', $corrige->first_name);
        $this->assertSame('Ghazli', $corrige->last_name);
    }

    // Une fois corrige, le profil quitte la liste : c'est ce qui permet a
    // l'admin de savoir ou il s'est arrete.
    public function test_a_corrected_profile_leaves_the_suspicious_list(): void
    {
        $profile = $this->makeProfile('Permis', 'B');

        $this->service->updateCandidateName($profile, 'Rostom', 'Ghazli');

        $this->assertCount(0, $this->service->listCandidateProfiles(['suspicious' => true])->items());
    }

    // --- Acces ---

    public function test_the_route_is_closed_to_non_admins(): void
    {
        $profile = $this->makeProfile('Permis', 'B');

        $this->patchJson("/api/admin/candidate-profiles/{$profile->id}/name", [
            'first_name' => 'Pirate',
            'last_name' => 'Anonyme',
        ])->assertStatus(401);

        $this->assertSame('Permis', $profile->fresh()->first_name);
    }
}
