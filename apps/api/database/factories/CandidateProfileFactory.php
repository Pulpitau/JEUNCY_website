<?php

namespace Database\Factories;

use App\Enums\ContractType;
use App\Enums\DrivingLicenseCategory;
use App\Enums\OfferSector;
use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateProfile>
 */
class CandidateProfileFactory extends Factory
{
    // Perpignan, pour que located() sans argument tombe dans le 66.
    public const PERPIGNAN_LAT = 42.70;

    public const PERPIGNAN_LNG = 2.90;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->candidate(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'city' => 'Perpignan',
            'postal_code' => '66000',
            'is_visible_in_cvtheque' => true,
        ];
    }

    // 20 ans : majeur, dans la tranche 18-20.
    public function adult(): static
    {
        return $this->state(fn () => ['birth_date' => now()->subYears(20)->toDateString()]);
    }

    // 15 ans : sous le seuil des 16 ans de Decouvrir.
    public function minor15(): static
    {
        return $this->state(fn () => ['birth_date' => now()->subYears(15)->toDateString()]);
    }

    public function aged(int $years): static
    {
        return $this->state(fn () => ['birth_date' => now()->subYears($years)->toDateString()]);
    }

    // Position PROFILE (geocodee). Les colonnes ne sont pas fillable : elles
    // sont posees apres la creation, comme le ferait GeocodingService.
    public function located(float $lat = self::PERPIGNAN_LAT, float $lng = self::PERPIGNAN_LNG): static
    {
        return $this->afterCreating(function (CandidateProfile $profile) use ($lat, $lng) {
            $profile->latitude = $lat;
            $profile->longitude = $lng;
            $profile->saveQuietly();
        });
    }

    // Position DEVICE (GPS), sans position PROFILE : sert a prouver que le
    // deck employeur ne la lit jamais.
    public function deviceLocated(float $lat = self::PERPIGNAN_LAT, float $lng = self::PERPIGNAN_LNG): static
    {
        return $this->afterCreating(function (CandidateProfile $profile) use ($lat, $lng) {
            $profile->device_latitude = $lat;
            $profile->device_longitude = $lng;
            $profile->device_located_at = now();
            $profile->saveQuietly();
        });
    }

    public function withPreferences(array $overrides = []): static
    {
        return $this->state(fn () => array_merge([
            'wanted_contract_types' => [ContractType::ALTERNANCE->value],
            'wanted_sectors' => [OfferSector::COMMERCE->value],
            'search_radius_km' => 30,
            'mobility_radius_km' => 30,
            'has_driving_license' => true,
            'driving_license_categories' => [DrivingLicenseCategory::B->value],
            'has_vehicle' => false,
            'available_from' => now()->addMonth()->toDateString(),
            'pitch' => 'Motivee, disponible des la rentree.',
        ], $overrides));
    }

    public function hiddenFromCvtheque(): static
    {
        return $this->state(fn () => ['is_visible_in_cvtheque' => false]);
    }

    public function showingPhoto(): static
    {
        return $this->state(fn () => [
            'photo_url' => '/storage/photos/portrait.jpg',
            'show_photo_to_employers' => true,
        ]);
    }
}
