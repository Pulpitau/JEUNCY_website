<?php

namespace Database\Factories;

use App\Enums\VerificationStatus;
use App\Models\CfaOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CfaOrganization>
 */
class CfaOrganizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->cfa(),
            'name' => 'CFA '.fake()->company(),
            'siret' => fake()->unique()->numerify('##############'),
            'city' => 'Perpignan',
            'postal_code' => '66000',
        ];
    }

    // Une ligne creee par factory nait PENDING (comme toute nouvelle
    // fiche) ; seules les lignes existantes au moment de la migration
    // 2026_09_22_100002 sont passees VERIFIED.
    public function verified(): static
    {
        return $this->afterCreating(function (CfaOrganization $cfa) {
            $cfa->verification_status = VerificationStatus::VERIFIED;
            $cfa->verified_at = now();
            $cfa->saveQuietly();
        });
    }

    public function located(float $lat = CandidateProfileFactory::PERPIGNAN_LAT, float $lng = CandidateProfileFactory::PERPIGNAN_LNG): static
    {
        return $this->afterCreating(function (CfaOrganization $cfa) use ($lat, $lng) {
            $cfa->latitude = $lat;
            $cfa->longitude = $lng;
            $cfa->saveQuietly();
        });
    }
}
