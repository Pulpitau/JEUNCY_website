<?php

namespace App\Models;

use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'cfa_organization_id', 'title', 'description', 'contract_type',
    'status', 'payment_status', 'location', 'city', 'published_at', 'expires_at',
])]
class JobOffer extends Model
{
    protected $table = 'job_offers';

    protected function casts(): array
    {
        return [
            'contract_type' => ContractType::class,
            'status' => JobOfferStatus::class,
            'payment_status' => PaymentStatus::class,
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
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
}
