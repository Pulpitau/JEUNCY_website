<?php

namespace Database\Factories;

use App\Enums\ExternalJobOfferStatus;
use App\Models\ExternalJobOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExternalJobOffer>
 */
class ExternalJobOfferFactory extends Factory
{
    public function definition(): array
    {
        $key = fake()->unique()->numerify('lba-######');

        return [
            'source' => ExternalJobOffer::SOURCE_LBA,
            'external_key' => $key,
            'partner_label' => 'La bonne alternance',
            'title' => 'Boulanger H/F en alternance',
            'description' => 'Fabrication du pain et des viennoiseries.',
            'company_name' => fake()->company(),
            'company_siret' => fake()->numerify('##############'),
            'city' => 'Perpignan',
            'postal_code' => '66000',
            'department' => '66',
            'apply_url' => 'https://labonnealternance.apprentissage.beta.gouv.fr/emploi/'.$key,
            'status' => ExternalJobOfferStatus::EXCLUDED,
            'exclusion_reason' => 'fixture',
            'import_batch' => 'lot-test',
            'last_seen_at' => now(),
            'published_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => ExternalJobOfferStatus::ACTIVE,
            'exclusion_reason' => null,
        ]);
    }

    // latitude / longitude SONT fillable sur ce modele (alimentees par
    // l'import), d'ou un simple state.
    public function located(float $lat = CandidateProfileFactory::PERPIGNAN_LAT, float $lng = CandidateProfileFactory::PERPIGNAN_LNG): static
    {
        return $this->state(fn () => ['latitude' => $lat, 'longitude' => $lng]);
    }
}
