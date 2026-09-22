<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\CompanyService;
use App\Services\TrainingOrganizationDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Une ecole qui se presente en entreprise est refusee.
 *
 * Depuis que l'espace entreprise est gratuit et l'inscription CFA fermee,
 * c'est LA porte a surveiller. Les cas « vrai employeur » comptent autant
 * que les cas « ecole » : un faux positif ferme la porte a une entreprise
 * qui recrute.
 */
class TrainingOrganizationDetectorTest extends TestCase
{
    use RefreshDatabase;

    private TrainingOrganizationDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = $this->app->make(TrainingOrganizationDetector::class);
    }

    // ------------------------------------------------------------------
    // Le nom
    // ------------------------------------------------------------------

    public static function nomsDEcoles(): array
    {
        return [
            ['CFA du Batiment 66'],
            ['École Supérieure de Commerce de Perpignan'],
            ['Campus Numérique Occitanie'],
            ['Académie des Métiers'],
            ['IDA Formation'],
            ['Lycée Jean Lurçat'],
            ['IUT de Perpignan'],
            ['Centre d\'Apprentissage du Roussillon'],
            ['Bachelor Marketing Digital'],
        ];
    }

    #[DataProvider('nomsDEcoles')]
    public function test_a_school_name_is_recognized(string $name): void
    {
        $this->assertNotNull($this->detector->reasonFor($name, null, null), $name);
    }

    public static function nomsDEmployeurs(): array
    {
        return [
            ['NexaTech'],
            ['Auto-école du Roussillon'],
            ['Auto école Catalane'],
            ['Institut de beauté Léa'],
            ['Boulangerie Formica'],      // « Formica » n'est pas « formation »
            ['Maître Restaurateur SAS'],
            ['Campusse Immobilier'],      // « campus » n'est pas un mot entier ici
        ];
    }

    #[DataProvider('nomsDEmployeurs')]
    public function test_an_employer_name_passes(string $name): void
    {
        $this->assertNull($this->detector->reasonFor($name, null, null), $name);
    }

    // ------------------------------------------------------------------
    // La description
    // ------------------------------------------------------------------

    public function test_the_vocabulary_of_a_school_in_the_description_is_recognized(): void
    {
        $reason = $this->detector->reasonFor(
            'Talents & Co',
            "Nous recherchons pour l'une de nos entreprises partenaires un alternant en BTS MCO. Formation prise en charge, rentrée 2026.",
            null,
        );

        $this->assertNotNull($reason);
        $this->assertStringContainsString('entreprises partenaires', $reason);
    }

    public function test_an_employer_talking_about_training_its_staff_passes(): void
    {
        $this->assertNull($this->detector->reasonFor(
            'NexaTech',
            'PME de 40 personnes. Formation assurée en interne, tutorat par un développeur senior.',
            null,
        ));
    }

    // ------------------------------------------------------------------
    // Le registre des entreprises (NAF)
    // ------------------------------------------------------------------

    private function fakeNaf(string $siret, string $naf): void
    {
        $this->registreEntreprises = fn () => Http::response([
            'results' => [[
                'nom_complet' => 'SOCIETE TEST',
                'activite_principale' => '70.10Z', // le siege, une holding
                'matching_etablissements' => [['siret' => $siret, 'activite_principale' => $naf]],
            ]],
            'total_results' => 1,
        ]);
    }

    public function test_a_higher_education_naf_code_is_blocked(): void
    {
        $this->fakeNaf('12345678900019', '85.42Z');

        $reason = $this->detector->reasonFor('Sunrise', null, '12345678900019');

        $this->assertNotNull($reason);
        $this->assertStringContainsString('85.42Z', $reason);
    }

    // L'etablissement prime sur l'unite legale : le siege est une holding,
    // mais l'etablissement inscrit est l'ecole.
    public function test_the_establishment_code_wins_over_the_head_office(): void
    {
        $this->fakeNaf('12345678900019', '85.59A');

        $this->assertNotNull($this->detector->reasonFor('Sunrise', null, '12345678900019'));
    }

    public function test_a_driving_school_naf_code_passes(): void
    {
        $this->fakeNaf('12345678900019', '85.53Z');

        $this->assertNull($this->detector->reasonFor('Sunrise', null, '12345678900019'));
    }

    public function test_a_nursery_naf_code_passes(): void
    {
        $this->fakeNaf('12345678900019', '85.10Z');

        $this->assertNull($this->detector->reasonFor('Les Petits Pas', null, '12345678900019'));
    }

    public function test_a_software_company_naf_code_passes(): void
    {
        $this->fakeNaf('12345678900019', '62.01Z');

        $this->assertNull($this->detector->reasonFor('NexaTech', null, '12345678900019'));
    }

    public function test_a_registry_outage_never_blocks_a_registration(): void
    {
        $this->registreEntreprises = fn () => Http::response(null, 503);

        $this->assertNull($this->detector->reasonFor('NexaTech', null, '12345678900019'));
    }

    public function test_no_siret_means_no_registry_lookup(): void
    {
        $this->assertNull($this->detector->reasonFor('NexaTech', null, null));

        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // Par HTTP : la fiche entreprise
    // ------------------------------------------------------------------

    private function companyUser(): User
    {
        return User::create(['email' => 'rh@example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
    }

    public function test_creating_a_company_profile_as_a_school_is_refused(): void
    {
        $this->actingAs($this->companyUser(), 'api')
            // Le SIRET est desormais obligatoire (modele match, MOBILE.md
            // §4.0) : sans lui, la requete serait refusee en validation avant
            // meme d'atteindre le detecteur d'ecoles.
            ->postJson('/api/company', ['name' => 'CFA des Métiers du Sud', 'siret' => '73282932000074'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TRAINING_ORGANIZATION_NOT_ALLOWED');

        $this->assertDatabaseCount('companies', 0);
    }

    public function test_renaming_a_company_into_a_school_is_refused(): void
    {
        $user = $this->companyUser();
        $this->app->make(CompanyService::class)->createForUser($user, ['name' => 'NexaTech']);

        $this->actingAs($user->fresh(), 'api')
            ->patchJson('/api/company', ['name' => 'NexaTech Formation'])
            ->assertStatus(403);

        $this->assertSame('NexaTech', $user->fresh()->company->name);
    }

    public function test_updating_only_the_city_does_not_consult_the_registry(): void
    {
        $user = $this->companyUser();
        $this->app->make(CompanyService::class)->createForUser($user, ['name' => 'NexaTech', 'siret' => '12345678900019']);
        Http::fake(); // remet le compteur de requetes a zero apres la creation

        $this->actingAs($user->fresh(), 'api')
            ->patchJson('/api/company', ['city' => 'Toulouse'])
            ->assertOk();

        Http::assertNothingSent();
    }

    public function test_a_real_employer_creates_its_profile_normally(): void
    {
        $this->actingAs($this->companyUser(), 'api')
            // SIRET a cle de Luhn correcte : depuis le modele match, une
            // faute de frappe est refusee a la saisie (App\Rules\ValidSiret).
            ->postJson('/api/company', ['name' => 'Auto-école du Roussillon', 'siret' => '73282932000074'])
            ->assertStatus(201);
    }
}
