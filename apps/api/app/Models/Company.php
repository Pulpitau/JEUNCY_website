<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// trial_started_at et trial_offers_count sont volontairement absents de ce
// tableau : geres uniquement par JobOfferService::publishViaTrialForUser
// (affectation directe, jamais via mass-assignment depuis une requete cliente).
#[Fillable(['user_id', 'name', 'siret', 'description', 'logo_url', 'website', 'address', 'city', 'postal_code', 'work_mode', 'is_public'])]
class Company extends Model
{
    protected $table = 'companies';

    // Masques par defaut : la fiche entreprise est servie telle quelle sur des
    // routes SANS authentification (voir PublicCompanyController, et la
    // relation chargee avec chaque offre publique dans JobOfferService), donc
    // tout champ visible ici est lisible par n'importe qui.
    //
    // - siret / user_id : identifiants, inutiles a l'affichage public ;
    //   user_id permettrait en plus d'enumerer les comptes.
    // - trial_* : etat commercial interne. Les exposer revelait a tout
    //   visiteur — y compris un concurrent — quelles entreprises sont en
    //   periode d'essai gratuite et combien d'offres il leur reste.
    //
    // Le proprietaire et l'admin les recuperent via makeVisible (voir
    // CompanyService::showForUser et AdminService).
    protected $hidden = ['user_id', 'siret', 'trial_started_at', 'trial_offers_count'];

    // Rendus visibles pour le proprietaire de la fiche et pour l'admin :
    // le formulaire "Mon entreprise" doit reafficher le SIRET saisi, et le
    // tableau de bord a besoin de l'etat d'essai pour proposer la
    // publication gratuite.
    public const OWNER_VISIBLE = ['siret', 'trial_started_at', 'trial_offers_count'];

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
