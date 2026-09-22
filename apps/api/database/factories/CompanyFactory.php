<?php

namespace Database\Factories;

use App\Enums\VerificationStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->company(),
            'name' => fake()->company(),
            // Un SIRET a 14 chiffres, unique ; la cle de Luhn n'est pas
            // verifiee par la base, seulement par ValidSiret (lot A).
            'siret' => fake()->unique()->numerify('##############'),
            'city' => 'Perpignan',
            'postal_code' => '66000',
        ];
    }

    // verification_status n'est pas fillable : pose apres la creation,
    // comme le ferait CompanyVerificationService.
    public function verified(): static
    {
        return $this->afterCreating(function (Company $company) {
            $company->verification_status = VerificationStatus::VERIFIED;
            $company->verified_at = now();
            $company->saveQuietly();
        });
    }

    public function rejected(?string $note = 'SIRET invalide'): static
    {
        return $this->afterCreating(function (Company $company) use ($note) {
            $company->verification_status = VerificationStatus::REJECTED;
            $company->verification_note = $note;
            $company->saveQuietly();
        });
    }

    public function located(float $lat = CandidateProfileFactory::PERPIGNAN_LAT, float $lng = CandidateProfileFactory::PERPIGNAN_LNG): static
    {
        return $this->afterCreating(function (Company $company) use ($lat, $lng) {
            $company->latitude = $lat;
            $company->longitude = $lng;
            $company->saveQuietly();
        });
    }
}
