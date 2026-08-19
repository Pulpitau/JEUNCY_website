<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['email', 'password_hash', 'google_id', 'role', 'is_suspended', 'last_login_at'])]
#[Hidden(['password_hash'])]
class User extends Authenticatable
{
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
        ];
    }

    // Domaine des emails d'anonymisation. Un compte ayant des paiements ne
    // peut pas etre supprime (conservation comptable) : AccountService le
    // reecrit en compte-supprime-{id}@jeuncy.invalid. Le TLD .invalid est
    // reserve par la RFC 2606 et ne peut donc jamais appartenir a un vrai
    // utilisateur — c'est ce qui rend ce marqueur fiable.
    public const DELETED_EMAIL_DOMAIN = '@jeuncy.invalid';

    // Comptes reels, hors vestiges comptables de comptes supprimes. A utiliser
    // partout ou l'on compte ou liste "les utilisateurs" pour un humain :
    // sans ca, le back-office affiche des effectifs qui ne correspondent a
    // aucune personne existante.
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('email', 'not like', '%'.self::DELETED_EMAIL_DOMAIN);
    }

    public function isDeletedAccount(): bool
    {
        return str_ends_with($this->email, self::DELETED_EMAIL_DOMAIN);
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

    public function getAuthPassword(): ?string
    {
        return $this->password_hash;
    }
}
