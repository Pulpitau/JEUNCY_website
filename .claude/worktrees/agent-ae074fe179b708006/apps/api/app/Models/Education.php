<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['candidate_profile_id', 'degree', 'school', 'field_of_study', 'start_date', 'end_date'])]
class Education extends Model
{
    // "education" est un nom indenombrable en anglais : la pluralisation
    // automatique d'Eloquent ne produit pas "educations", d'ou le nom explicite.
    protected $table = 'educations';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }
}
