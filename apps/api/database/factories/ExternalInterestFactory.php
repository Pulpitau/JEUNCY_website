<?php

namespace Database\Factories;

use App\Enums\ExternalInterestDecision;
use App\Models\CandidateProfile;
use App\Models\ExternalInterest;
use App\Models\ExternalJobOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExternalInterest>
 */
class ExternalInterestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory()->adult(),
            'external_job_offer_id' => ExternalJobOffer::factory()->active(),
            'decision' => ExternalInterestDecision::KEEP,
            'company_name' => 'Au pain dore',
            'title' => 'Boulanger H/F en alternance',
            'city' => 'Perpignan',
            'apply_url' => 'https://labonnealternance.apprentissage.beta.gouv.fr/emploi/1',
            'decided_at' => now(),
        ];
    }

    public function passed(): static
    {
        return $this->state(fn () => ['decision' => ExternalInterestDecision::PASS]);
    }

    public function done(): static
    {
        return $this->state(fn () => ['done_at' => now()]);
    }
}
