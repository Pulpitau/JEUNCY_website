<?php

namespace App\Models;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// interest_id, source et responded_at : rattachement au modele match (voir
// la migration 2026_09_22_100006). Poses par ApplicationService, jamais
// depuis le corps d'une requete cliente.
#[Fillable([
    'candidate_profile_id', 'job_offer_id', 'status', 'cover_letter', 'contact_phone',
    'generated_cv_id', 'cv_file_url', 'interest_id', 'source', 'responded_at',
])]
class Application extends Model
{
    protected $table = 'applications';

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'source' => ApplicationSource::class,
            'responded_at' => 'datetime',
        ];
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    public function jobOffer(): BelongsTo
    {
        return $this->belongsTo(JobOffer::class);
    }

    public function generatedCv(): BelongsTo
    {
        return $this->belongsTo(GeneratedCv::class);
    }

    public function interest(): BelongsTo
    {
        return $this->belongsTo(OfferInterest::class, 'interest_id');
    }
}
