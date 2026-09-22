<?php

namespace App\Models;

use App\Enums\ExternalInterestDecision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Geste du candidat sur une offre partenaire (KEEP / PASS). Les champs de
// l'offre sont denormalises parce que l'offre importee peut disparaitre a
// l'import de nuit suivant (voir la migration create_external_interests_table).
#[Fillable([
    'candidate_profile_id', 'external_job_offer_id', 'decision', 'company_siret',
    'company_name', 'title', 'city', 'apply_url', 'decided_at', 'done_at',
])]
class ExternalInterest extends Model
{
    use HasFactory;

    protected $table = 'external_interests';

    protected function casts(): array
    {
        return [
            'decision' => ExternalInterestDecision::class,
            'decided_at' => 'datetime',
            'done_at' => 'datetime',
        ];
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    public function externalJobOffer(): BelongsTo
    {
        return $this->belongsTo(ExternalJobOffer::class);
    }
}
