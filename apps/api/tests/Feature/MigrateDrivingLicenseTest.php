<?php

namespace Tests\Feature;

use App\Console\Commands\MigrateDrivingLicense;
use App\Enums\DrivingLicenseCategory;
use App\Models\CandidateProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reprise du texte libre « permis » vers les catégories structurees.
 *
 * Ces tests portent sur des textes REELS de la production : c'est la seule
 * façon de voir qu'une regle trop simple invente des catégories (« vehicule
 * a disposition » donne un « a » isole).
 */
class MigrateDrivingLicenseTest extends TestCase
{
    use RefreshDatabase;

    private function valeurs(?array $categories): array
    {
        return $categories === null ? [] : array_map(fn ($c) => $c->value, $categories);
    }

    public function test_permis_b_gives_category_b(): void
    {
        foreach (['Permis B', 'permis b, véhicule personnel', 'B', 'Titulaire du permis de conduire', 'Oui'] as $texte) {
            $this->assertSame(
                [DrivingLicenseCategory::B->value],
                $this->valeurs(MigrateDrivingLicense::categoriesFor($texte)),
                "Texte : {$texte}",
            );
        }
    }

    public function test_isolated_a_or_d_never_gives_a_category(): void
    {
        // Apres normalisation, ces deux textes contiennent un « a » et un
        // « d » isoles. Une regle lettre a lettre inventerait ici un permis
        // moto et un permis poids lourd.
        // null = non reconnu : le texte est liste dans le rapport, rien
        // n'est ecrit. Surtout pas un permis A.
        $this->assertNull(MigrateDrivingLicense::categoriesFor('Véhicule à disposition'));
        $this->assertSame(
            [DrivingLicenseCategory::B->value],
            $this->valeurs(MigrateDrivingLicense::categoriesFor("Titulaire d'un permis B et d'un véhicule")),
        );
    }

    public function test_each_family_is_recognized(): void
    {
        $attendus = [
            'Permis A2' => [DrivingLicenseCategory::A2],
            'permis moto' => [DrivingLicenseCategory::A],
            'BSR' => [DrivingLicenseCategory::AM],
            'Permis C, poids lourd' => [DrivingLicenseCategory::C],
            'Permis D' => [DrivingLicenseCategory::D],
        ];

        foreach ($attendus as $texte => $categories) {
            $this->assertSame($this->valeurs($categories), $this->valeurs(MigrateDrivingLicense::categoriesFor($texte)), "Texte : {$texte}");
        }

        // « permis be » ne doit pas etre lu comme « permis b ».
        $this->assertSame(
            [DrivingLicenseCategory::BE->value],
            $this->valeurs(MigrateDrivingLicense::categoriesFor('Permis BE')),
        );
    }

    public function test_a_refusal_is_read_as_no_license(): void
    {
        foreach (['Pas de permis', 'Permis B en cours', 'Non', 'sans permis'] as $texte) {
            $this->assertSame([], MigrateDrivingLicense::categoriesFor($texte), "Texte : {$texte}");
        }
    }

    public function test_a_negation_about_something_else_does_not_erase_the_license(): void
    {
        // « non » y porte sur le vehicule, pas sur le permis.
        $this->assertSame(
            [DrivingLicenseCategory::B->value],
            $this->valeurs(MigrateDrivingLicense::categoriesFor('Permis B, non véhiculé')),
        );
    }

    public function test_unknown_text_is_listed_not_written(): void
    {
        $this->assertNull(MigrateDrivingLicense::categoriesFor('Disponible immédiatement'));

        $profile = CandidateProfile::factory()->create(['driving_license' => 'Disponible immédiatement']);

        $this->artisan('candidates:migrate-driving-license --apply')->assertExitCode(0);

        $profile->refresh();
        $this->assertFalse($profile->has_driving_license);
        $this->assertNull($profile->driving_license_categories);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $profile = CandidateProfile::factory()->create(['driving_license' => 'Permis B']);

        $this->artisan('candidates:migrate-driving-license')->assertExitCode(0);

        $profile->refresh();
        $this->assertFalse($profile->has_driving_license);
        $this->assertNull($profile->driving_license_categories);
    }

    public function test_apply_keeps_text_column(): void
    {
        $profile = CandidateProfile::factory()->create(['driving_license' => 'Permis B']);

        $this->artisan('candidates:migrate-driving-license --apply')->assertExitCode(0);

        $profile->refresh();
        $this->assertTrue($profile->has_driving_license);
        $this->assertSame([DrivingLicenseCategory::B->value], $profile->driving_license_categories);
        // Conservee : elle alimente encore le gabarit de CV.
        $this->assertSame('Permis B', $profile->driving_license);
    }

    public function test_apply_does_not_look_like_a_profile_update(): void
    {
        // Le deck employeur classe par « profil mis a jour recemment » : une
        // reprise technique qui repasse sur 89 profils les ferait tous
        // passer pour actifs le meme jour.
        $profile = CandidateProfile::factory()->create(['driving_license' => 'Permis B']);
        $profile->timestamps = false;
        $profile->updated_at = now()->subYear();
        $profile->save();
        $avant = $profile->fresh()->updated_at;

        $this->artisan('candidates:migrate-driving-license --apply')->assertExitCode(0);

        $profile->refresh();
        $this->assertTrue($profile->has_driving_license);
        $this->assertTrue($avant->equalTo($profile->updated_at), 'La reprise ne doit pas rajeunir le profil.');
    }

    public function test_apply_records_an_explicit_refusal(): void
    {
        $profile = CandidateProfile::factory()->create(['driving_license' => 'Pas de permis']);

        $this->artisan('candidates:migrate-driving-license --apply')->assertExitCode(0);

        $profile->refresh();
        $this->assertFalse($profile->has_driving_license);
        $this->assertSame([], $profile->driving_license_categories);
    }
}
