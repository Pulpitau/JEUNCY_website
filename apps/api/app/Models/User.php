<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

// age_confirmed_at : date d'acceptation de la case « J'ai 15 ans ou plus »
// a l'inscription (posee par AuthService, null pour Google et les comptes
// anterieurs).
#[Fillable(['email', 'password_hash', 'google_id', 'role', 'is_suspended', 'last_login_at', 'deleted_account_at', 'age_confirmed_at'])]
#[Hidden(['password_hash'])]
class User extends Authenticatable
{
    use HasFactory;

    protected $table = 'users';

    // Le defaut DB (0) ne se reflete pas sur un modele fraichement cree en
    // memoire (User::create() ne relit pas la ligne) : sans ca, un token emis
    // juste apres l'inscription embarque tv=null alors qu'un rechargement
    // depuis la base donne 0, faisant echouer la comparaison stricte dans
    // JwtGuard/AuthService::refreshTokens des la premiere requete.
    protected $attributes = [
        'token_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'role' => UserRole::class,
            'is_suspended' => 'boolean',
            'last_login_at' => 'datetime',
            'deleted_account_at' => 'datetime',
            'age_confirmed_at' => 'datetime',
        ];
    }

    // Domaine reserve aux adresses d'anonymisation (voir
    // AccountService::deleteAccount). Interdit a l'inscription : sans cette
    // reserve, un tiers pouvait s'inscrire avec une telle adresse.
    //
    // Ce domaine ne sert PLUS a determiner si un compte est supprime — c'est
    // deleted_account_at qui porte cet etat. Deduire un etat d'une chaine que
    // l'utilisateur controle etait la cause de deux failles (compte invisible
    // de la moderation, et suppression RGPD bloquable par pre-enregistrement
    // de l'adresse previsible).
    public const DELETED_EMAIL_DOMAIN = '@jeuncy.invalid';

    // Comptes reels, hors vestiges comptables de comptes supprimes. A utiliser
    // partout ou l'on compte ou liste "les utilisateurs" pour un humain :
    // sans ca, le back-office affiche des effectifs qui ne correspondent a
    // aucune personne existante.
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->whereNull('deleted_account_at');
    }

    public function isDeletedAccount(): bool
    {
        return $this->deleted_account_at !== null;
    }

    public function candidateProfile(): HasOne
    {
        return $this->hasOne(CandidateProfile::class);
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class);
    }

    public function cfaOrganization(): HasOne
    {
        return $this->hasOne(CfaOrganization::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hostedVideoRooms(): HasMany
    {
        return $this->hasMany(VideoRoom::class, 'host_id');
    }

    public function participatedVideoRooms(): HasMany
    {
        return $this->hasMany(VideoRoom::class, 'participant_id');
    }

    // Blocages emis par ce compte / recus par ce compte (MOBILE.md §7).
    // BlockService::blockedUserIdsFor les combine dans les deux sens.
    public function blocks(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocker_user_id');
    }

    public function blockedBy(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocked_user_id');
    }

    public function getAuthPassword(): ?string
    {
        return $this->password_hash;
    }
}
