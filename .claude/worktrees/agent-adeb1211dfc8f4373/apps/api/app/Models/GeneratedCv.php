<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['candidate_profile_id', 'file_url'])]
class GeneratedCv extends Model
{
    protected $table = 'generated_cvs';

    // generated_at est peuplee par defaut MySQL (useCurrent() en migration).
    public $timestamps = false;

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }
}
