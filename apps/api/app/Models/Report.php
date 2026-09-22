<?php

namespace App\Models;

use App\Enums\ReportContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Signalement emis par un compte (MOBILE.md §7). handled_by / handled_at ne
// sont poses que par l'equipe (admin des signalements, hors lot 1).
#[Fillable([
    'reporter_user_id', 'reported_user_id', 'job_offer_id', 'context', 'reason', 'details',
    'handled_by', 'handled_at',
])]
class Report extends Model
{
    use HasFactory;

    protected $table = 'reports';

    protected function casts(): array
    {
        return [
            'context' => ReportContext::class,
            'handled_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function reported(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function jobOffer(): BelongsTo
    {
        return $this->belongsTo(JobOffer::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
