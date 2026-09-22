<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Blocage d'un compte par un autre (MOBILE.md §7), applique dans les deux
// sens par BlockService::blockedUserIdsFor.
#[Fillable(['blocker_user_id', 'blocked_user_id'])]
class UserBlock extends Model
{
    use HasFactory;

    protected $table = 'user_blocks';

    // Une seule date, posee par la base (useCurrent) : pas d'updated_at.
    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_user_id');
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_user_id');
    }
}
