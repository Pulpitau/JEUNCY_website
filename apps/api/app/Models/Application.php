<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['candidate_profile_id', 'job_offer_id', 'status', 'cover_letter', 'contact_phone', 'generated_cv_id', 'cv_file_url'])]
class Application extends Model
{
    protected $table = 'applications';

    protected function casts(): array
    {
        return ['status' => ApplicationStatus::class];
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
}
