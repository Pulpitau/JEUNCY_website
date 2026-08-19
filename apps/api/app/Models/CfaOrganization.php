<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// trial_started_at et trial_offers_count sont volontairement absents de ce
// tableau : geres uniquement par JobOfferService::publishViaTrialForUser
// (affectation directe, jamais via mass-assignment depuis une requete cliente).
#[Fillable(['user_id', 'name', 'siret', 'nda_number', 'qualiopi_number', 'description', 'diplomas_offered', 'diploma_level', 'training_mode', 'logo_url', 'website', 'address', 'city', 'postal_code', 'is_public'])]
class CfaOrganization extends Model
{
    protected $table = 'cfa_organizations';

    // Meme raisonnement que Company::$hidden (voir le commentaire detaille
    // la-bas) : fiche servie sur des routes publiques, donc on ne laisse
    // sortir que ce qui sert a l'affichage.
    //
    // nda_number et qualiopi_number restent VISIBLES : ce sont des
    // certifications qu'un CFA met en avant, elles rassurent le candidat et
    // sont deja publiques par nature (registres officiels).
    protected $hidden = ['user_id', 'siret', 'trial_started_at', 'trial_offers_count', 'is_public'];

    public const OWNER_VISIBLE = ['siret', 'trial_started_at', 'trial_offers_count', 'is_public'];

    protected function casts(): array
    {
        return [
            'trial_started_at' => 'datetime',
            'is_public' => 'boolean',
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
