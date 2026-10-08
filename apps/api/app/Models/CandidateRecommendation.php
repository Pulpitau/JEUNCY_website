<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Un CFA recommande un de ses candidats a une entreprise partenaire, pour
// une offre precise (espace CFA, chantier 2, 2026-10-08). Ne cree jamais
// d'interet employeur a la place de l'entreprise — c'est juste le signal
// "regarde cette carte", stocke pour eviter de notifier deux fois le meme
// couple candidat/offre.
#[Fillable(['cfa_organization_id', 'candidate_profile_id', 'job_offer_id'])]
class CandidateRecommendation extends Model
{
    use HasFactory;

    protected $table = 'candidate_recommendations';

    public function cfaOrganization(): BelongsTo
    {
        return $this->belongsTo(CfaOrganization::class);
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    public function jobOffer(): BelongsTo
    {
        return $this->belongsTo(JobOffer::class);
    }
}
