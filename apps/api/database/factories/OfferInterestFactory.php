<?php

namespace Database\Factories;

use App\Enums\InterestDecision;
use App\Enums\MatchClosedReason;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfferInterest>
 */
class OfferInterestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory()->adult(),
            'job_offer_id' => JobOffer::factory()->published(),
        ];
    }

    public function candidateLiked(): static
    {
        return $this->state(fn () => [
            'candidate_decision' => InterestDecision::LIKE,
            'candidate_decided_at' => now(),
        ]);
    }

    public function candidatePassed(): static
    {
        return $this->state(fn () => [
            'candidate_decision' => InterestDecision::PASS,
            'candidate_decided_at' => now(),
        ]);
    }

    public function employerLiked(): static
    {
        return $this->state(fn () => [
            'employer_decision' => InterestDecision::LIKE,
            'employer_decided_at' => now(),
        ]);
    }

    public function employerPassed(): static
    {
        return $this->state(fn () => [
            'employer_decision' => InterestDecision::PASS,
            'employer_decided_at' => now(),
        ]);
    }

    // Les deux ont dit oui et les deux ont ete prevenus (sans worker, la
    // notification part dans l'appel qui cree le match).
    public function matched(): static
    {
        return $this->candidateLiked()->employerLiked()->state(fn () => [
            'matched_at' => now(),
            'candidate_notified_at' => now(),
            'employer_notified_at' => now(),
        ]);
    }

    public function closed(MatchClosedReason $reason = MatchClosedReason::OFFER_ARCHIVED): static
    {
        return $this->state(fn () => [
            'closed_at' => now(),
            'closed_reason' => $reason,
        ]);
    }
}
