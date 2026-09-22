<?php

namespace Database\Factories;

use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Enums\OfferSector;
use App\Enums\PaymentStatus;
use App\Models\CfaOrganization;
use App\Models\Company;
use App\Models\JobOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobOffer>
 */
class JobOfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Une offre appartient a une Company OU un CfaOrganization :
            // Company par defaut, forCfa() pour l'autre cas.
            'company_id' => Company::factory(),
            'cfa_organization_id' => null,
            'title' => 'Vendeur conseil en alternance',
            'description' => 'Accueil, conseil et mise en rayon.',
            'contract_type' => ContractType::ALTERNANCE,
            'status' => JobOfferStatus::DRAFT,
            'payment_status' => PaymentStatus::PENDING,
            'city' => 'Perpignan',
            'postal_code' => '66000',
            'sector' => OfferSector::COMMERCE,
            'recruitment_radius_km' => 30,
            'minimum_age' => 16,
        ];
    }

    public function forCfa(?int $cfaOrganizationId = null): static
    {
        return $this->state(fn () => [
            'company_id' => null,
            'cfa_organization_id' => $cfaOrganizationId ?? CfaOrganization::factory(),
        ]);
    }

    // Publiee gratuitement (mode de la production depuis le 2026-09-15) :
    // pas d'echeance, candidatures incluses.
    public function published(): static
    {
        return $this->state(fn () => [
            'status' => JobOfferStatus::PUBLISHED,
            'payment_status' => PaymentStatus::FREE,
            'published_at' => now(),
            'applications_unlocked_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => JobOfferStatus::ARCHIVED]);
    }

    // Coordonnees hors fillable : posees apres la creation, comme le ferait
    // GeocodingService.
    public function located(float $lat = CandidateProfileFactory::PERPIGNAN_LAT, float $lng = CandidateProfileFactory::PERPIGNAN_LNG): static
    {
        return $this->afterCreating(function (JobOffer $offer) use ($lat, $lng) {
            $offer->latitude = $lat;
            $offer->longitude = $lng;
            $offer->saveQuietly();
        });
    }
}
