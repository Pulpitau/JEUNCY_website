<?php

namespace App\Models;

use App\Enums\ExternalJobOfferStatus;
use App\Enums\WorkMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// Offre importee d'une source externe (La bonne alternance). Voir la
// migration create_external_job_offers_table pour le pourquoi d'une table
// separee de job_offers.
#[Fillable([
    'source', 'external_key', 'partner_label', 'partner_job_id',
    'title', 'description', 'company_name', 'company_siret', 'company_naf',
    'company_naf_label', 'company_size', 'company_website',
    'address', 'postal_code', 'city', 'department', 'latitude', 'longitude',
    'work_mode', 'contract_start', 'contract_duration_months',
    'target_diploma_level', 'target_diploma_label', 'rome_codes', 'opening_count',
    'apply_url', 'is_delegated', 'published_at', 'expires_at',
    'status', 'exclusion_reason', 'excluded_by_admin_at', 'import_batch', 'last_seen_at',
])]
class ExternalJobOffer extends Model
{
    protected $table = 'external_job_offers';

    public const SOURCE_LBA = 'lba';

    // Ce que le public voit. Volontairement sans SIRET ni NAF : ce sont des
    // donnees de filtrage, pas de presentation.
    public const PUBLIC_COLUMNS = [
        'id', 'source', 'partner_label', 'title', 'description', 'company_name',
        'company_size', 'company_website', 'company_naf_label', 'city', 'postal_code',
        'department', 'work_mode', 'contract_start', 'contract_duration_months',
        'target_diploma_level', 'target_diploma_label', 'rome_codes', 'opening_count',
        'apply_url', 'published_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExternalJobOfferStatus::class,
            'work_mode' => WorkMode::class,
            'rome_codes' => 'array',
            'is_delegated' => 'boolean',
            'contract_start' => 'date',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'excluded_by_admin_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }
}
