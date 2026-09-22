<?php

namespace App\Models;

use App\Enums\CompensationPeriod;
use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Enums\OfferSector;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// latitude / longitude sont volontairement absentes de ce tableau : elles
// sont geocodees par GeocodingService a partir du code postal, jamais
// acceptees d'une requete cliente.
#[Fillable([
    'company_id', 'cfa_organization_id', 'title', 'description', 'contract_type',
    'status', 'payment_status', 'location', 'city', 'work_mode', 'compensation',
    'compensation_amount', 'compensation_period',
    'experience_level', 'benefits', 'diploma_level', 'training_rhythm', 'published_at',
    'expires_at', 'applications_unlocked_at',
    'postal_code', 'recruitment_radius_km', 'sector', 'schedule', 'start_date',
    'minimum_age', 'requires_driving_license', 'missions',
])]
class JobOffer extends Model
{
    use HasFactory;

    protected $table = 'job_offers';

    protected function casts(): array
    {
        return [
            'contract_type' => ContractType::class,
            'compensation_period' => CompensationPeriod::class,
            'status' => JobOfferStatus::class,
            'payment_status' => PaymentStatus::class,
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'applications_unlocked_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'recruitment_radius_km' => 'integer',
            'sector' => OfferSector::class,
            'start_date' => 'date',
            'minimum_age' => 'integer',
            'requires_driving_license' => 'boolean',
            'missions' => 'array',
        ];
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function cfaOrganization(): BelongsTo
    {
        return $this->belongsTo(CfaOrganization::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function offerInterests(): HasMany
    {
        return $this->hasMany(OfferInterest::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'job_offer_skills');
    }
}
