<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CandidateProfile;
use App\Models\Skill;
use App\Models\Software;
use App\Models\User;
use App\Services\CvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Le CV genere doit tenir sur UNE page, quel que soit le profil.
//
// Ce fichier existe parce que la garantie a lache deux fois en production sur
// des profils reels, alors que des essais ponctuels passaient. Les cas ci-
// dessous couvrent la forme qui a casse (profil moyen, beaucoup de
// competences, peu d'experiences) et celles qui pourraient casser : champs
// demesures, mots insecables, et profil quasi vide que le gabarit "aere"
// volontairement en multipliant les espacements.
//
// Ces tests rendent de vrais PDF : ils sont lents mais c'est le seul niveau ou
// la garantie a un sens. Verifier le HTML intermediaire ne dirait rien du
// decoupage en pages, qui est fait par dompdf.
class CvOnePageGuaranteeTest extends TestCase
{
    use RefreshDatabase;

    private CvService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(CvService::class);
    }

    private function makeProfile(array $attributes = []): CandidateProfile
    {
        static $n = 0;
        $n++;

        $user = User::create([
            'email' => "cv-guarantee-{$n}@example.com",
            'password_hash' => 'x',
            'role' => UserRole::CANDIDATE,
        ]);

        return CandidateProfile::create(array_merge([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Candidat',
        ], $attributes));
    }

    private function assertFitsOnOnePage(CandidateProfile $profile, string $context): void
    {
        $pdf = $this->service->renderPdfFor($profile->fresh());
        $pages = preg_match_all('#/Type\s*/Page[^s]#', $pdf);

        $this->assertSame(
            1,
            $pages,
            "Le CV doit tenir sur une page ({$context}), il en fait {$pages}.",
        );
    }

    private function addSkills(CandidateProfile $profile, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $profile->skills()->attach(Skill::firstOrCreate(['name' => "Competence {$i}"])->id);
        }
    }

    private function addSoftware(CandidateProfile $profile, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $profile->software()->attach(Software::firstOrCreate(['name' => "Logiciel {$i}"])->id);
        }
    }

    private function addEducations(CandidateProfile $profile, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $profile->educations()->create([
                'degree' => "Diplome {$i}",
                'school' => "Etablissement {$i}",
                'start_date' => '2020-01-01',
                'end_date' => '2021-12-31',
            ]);
        }
    }

    private function addExperiences(CandidateProfile $profile, int $count, int $lines): void
    {
        $line = 'Ligne de description assez longue pour occuper toute la largeur de la colonne';

        for ($i = 1; $i <= $count; $i++) {
            $profile->experiences()->create([
                'title' => "Poste numero {$i}",
                'company' => "Entreprise {$i}",
                'location' => 'Perpignan',
                'start_date' => '2022-01-01',
                'end_date' => '2023-01-01',
                'description' => $lines > 0 ? implode("\n", array_fill(0, $lines, $line)) : null,
            ]);
        }
    }

    // --- La forme qui a echoue en production ---

    // Peu d'experiences mais beaucoup de competences : le score de densite la
    // jugeait "vide", lui appliquait l'inflation maximale des espacements, et
    // le corps du CV — une ligne de table insecable — basculait en page 2.
    public function test_profile_with_many_skills_and_few_experiences_fits(): void
    {
        $profile = $this->makeProfile([
            'headline' => 'Conseiller Commercial',
            'driving_license' => 'B',
            'phone' => '+33630154312',
            'address' => '12 Rue Teresa Rebull',
            'postal_code' => '66300',
            'city' => 'Saint Jean Lasseille',
            'birth_date' => '2001-01-01',
        ]);
        $this->addSkills($profile, 12);
        $this->addSoftware($profile, 4);
        $this->addEducations($profile, 3);
        $this->addExperiences($profile, 1, 0);
        $profile->languages()->create(['name' => 'Anglais', 'level' => 'B2']);

        $this->assertFitsOnOnePage($profile, 'profil moyen, 12 competences');
    }

    // --- Volume brut ---

    public function test_very_loaded_profile_fits(): void
    {
        $profile = $this->makeProfile([
            'headline' => 'Responsable commercial et marketing digital',
            'bio' => str_repeat('Passionne par le commerce et la relation client. ', 25),
            'hobbies' => 'Photographie, Video, Crossfit, Randonnee, Cuisine, Lecture',
        ]);
        $this->addExperiences($profile, 8, 6);
        $this->addSkills($profile, 20);
        $this->addEducations($profile, 5);

        $this->assertFitsOnOnePage($profile, '8 experiences de 6 lignes, 20 competences');
    }

    public function test_extremely_loaded_profile_fits(): void
    {
        $profile = $this->makeProfile(['bio' => str_repeat('Texte de presentation. ', 60)]);
        $this->addExperiences($profile, 15, 8);
        $this->addSkills($profile, 40);

        $this->assertFitsOnOnePage($profile, '15 experiences de 8 lignes, 40 competences');
    }

    // --- Champs demesures ---

    public function test_profile_with_an_enormous_bio_fits(): void
    {
        $profile = $this->makeProfile(['bio' => str_repeat('a', 3000)]);

        $this->assertFitsOnOnePage($profile, 'bio de 3000 caracteres');
    }

    // Un mot sans espace ne peut pas etre renvoye a la ligne : il deborde la
    // colonne et peut casser la mise en page a toutes les echelles.
    public function test_profile_with_unbreakable_words_fits(): void
    {
        $profile = $this->makeProfile([
            'bio' => str_repeat('X', 200),
            'portfolio_url' => 'https://exemple.fr/'.str_repeat('segment-tres-long-', 12),
        ]);
        $profile->experiences()->create([
            'title' => str_repeat('T', 120),
            'company' => str_repeat('E', 120),
            'start_date' => '2023-01-01',
            'description' => str_repeat('M', 300),
        ]);

        $this->assertFitsOnOnePage($profile, 'mots insecables de 120 a 300 caracteres');
    }

    public function test_profile_with_a_very_long_identity_fits(): void
    {
        $profile = $this->makeProfile([
            'first_name' => str_repeat('Jean-Baptiste', 3),
            'last_name' => str_repeat('De La Tour Montmorency', 2),
            'headline' => str_repeat('Charge de developpement commercial et marketing ', 4),
            'address' => str_repeat('12 avenue de la Republique prolongee ', 3),
        ]);
        $this->addSkills($profile, 12);
        $this->addExperiences($profile, 3, 3);

        $this->assertFitsOnOnePage($profile, 'identite et titre tres longs');
    }

    // --- Profils peu fournis, que le gabarit aere volontairement ---

    public function test_almost_empty_profile_fits(): void
    {
        $this->assertFitsOnOnePage($this->makeProfile(), 'prenom et nom seulement');
    }

    public function test_sparse_profile_with_one_item_per_section_fits(): void
    {
        $profile = $this->makeProfile(['bio' => 'Deux lignes de presentation, pas plus.']);
        $this->addSkills($profile, 1);
        $this->addExperiences($profile, 1, 1);
        $profile->languages()->create(['name' => 'Anglais', 'level' => 'A2']);

        $this->assertFitsOnOnePage($profile, 'un seul element par rubrique');
    }

    // --- Profils realistes de candidats Jeuncy ---

    public function test_high_school_candidate_without_experience_fits(): void
    {
        $profile = $this->makeProfile([
            'first_name' => 'Inaya',
            'last_name' => 'Ben Abdeslem',
            'headline' => 'Alternance en vente',
            'birth_date' => '2008-05-01',
            'city' => 'Millas',
        ]);
        $profile->educations()->create([
            'degree' => 'Bac Pro Vente Accueil',
            'school' => 'Lycee Alfred Sauvy',
            'start_date' => '2023-09-01',
        ]);

        $this->assertFitsOnOnePage($profile, 'lyceen sans experience');
    }

    public function test_student_with_several_seasonal_jobs_fits(): void
    {
        $profile = $this->makeProfile([
            'first_name' => 'Fanny',
            'last_name' => 'Blasco',
            'headline' => 'Alternance en design graphique',
            'bio' => 'A la recherche d une alternance en design graphique et communication.',
            'driving_license' => 'B',
        ]);
        $this->addExperiences($profile, 5, 3);
        $this->addSkills($profile, 6);
        $this->addSoftware($profile, 7);

        $this->assertFitsOnOnePage($profile, '5 jobs saisonniers');
    }

    public function test_multilingual_and_well_filled_candidate_fits(): void
    {
        $profile = $this->makeProfile(['headline' => 'Charge de communication']);
        $this->addExperiences($profile, 6, 4);
        $this->addSkills($profile, 20);
        foreach (['Anglais', 'Espagnol', 'Catalan', 'Portugais'] as $langue) {
            $profile->languages()->create(['name' => $langue, 'level' => 'B2']);
        }

        $this->assertFitsOnOnePage($profile, 'profil bilingue tres fourni');
    }

    // --- Contrat de sortie ---

    // Sans ce marqueur, impossible de distinguer sur un PDF de production un
    // correctif inefficace d'un correctif jamais deploye — doute qui a coute
    // deux allers-retours.
    public function test_generated_pdf_carries_the_layout_version(): void
    {
        $pdf = $this->service->renderPdfFor($this->makeProfile());

        // dompdf ecrit ses metadonnees en UTF-16BE : la version y apparait avec
        // un octet nul entre chaque caractere.
        $utf16 = mb_convert_encoding(CvService::LAYOUT_VERSION, 'UTF-16BE', 'UTF-8');

        $this->assertStringContainsString($utf16, $pdf);
    }
}
