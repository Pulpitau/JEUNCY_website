<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['email', 'password_hash', 'google_id', 'role', 'is_suspended'])]
#[Hidden(['password_hash'])]
class User extends Authenticatable
{
    protected $table = 'users';

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'role' => UserRole::class,
            'is_suspended' => 'boolean',
        ];
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
