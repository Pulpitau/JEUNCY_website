<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// trial_started_at et trial_offers_count sont volontairement absents de ce
// tableau : geres uniquement par JobOfferService::publishViaTrialForUser
// (affectation directe, jamais via mass-assignment depuis une requete cliente).
#[Fillable(['user_id', 'name', 'siret', 'nda_number', 'qualiopi_number', 'description', 'diplomas_offered', 'diploma_level', 'training_mode', 'logo_url', 'website', 'address', 'city', 'postal_code'])]
class CfaOrganization extends Model
{
    protected $table = 'cfa_organizations';

    protected function casts(): array
    {
        return [
            'trial_started_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobOffers(): HasMany
    {
        return $this->hasMany(JobOffer::class);
    }
}
