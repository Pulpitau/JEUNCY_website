<?php

namespace App\Models;

use App\Enums\InterestDecision;
use App\Enums\MatchClosedReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Une ligne par couple candidat / offre Jeuncy (MOBILE.md §5). Match = les
// deux decisions a LIKE, matched_at pose dans la meme transaction.
//
// Ne jamais serialiser ce modele tel quel vers un client : il porte la
// decision de l'AUTRE partie (un candidat ne doit pas lire
// employer_decision = PASS, ni l'inverse). Les services projettent
// {id, job_offer_id, candidate_profile_id, decision de l'appelant,
// decided_at, matched_at, application_id}.
#[Fillable([
    'candidate_profile_id', 'job_offer_id', 'candidate_decision', 'employer_decision',
    'candidate_decided_at', 'employer_decided_at', 'matched_at', 'application_id',
    'closed_at', 'closed_reason', 'candidate_notified_at', 'employer_notified_at',
])]
class OfferInterest extends Model
{
    use HasFactory;

    protected $table = 'offer_interests';

    protected function casts(): array
    {
        return [
            'candidate_decision' => InterestDecision::class,
            'employer_decision' => InterestDecision::class,
            'closed_reason' => MatchClosedReason::class,
            'candidate_decided_at' => 'datetime',
            'employer_decided_at' => 'datetime',
            'matched_at' => 'datetime',
            'closed_at' => 'datetime',
            'candidate_notified_at' => 'datetime',
            'employer_notified_at' => 'datetime',
        ];
    }

    // Lignes encore vivantes : ni archivage, ni suppression, ni retrait.
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    // Lignes ou les deux parties ont dit oui.
    public function scopeMatched(Builder $query): Builder
    {
        return $query->whereNotNull('matched_at');
    }

    public function isMatched(): bool
    {
        return $this->matched_at !== null;
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    public function jobOffer(): BelongsTo
    {
        return $this->belongsTo(JobOffer::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
