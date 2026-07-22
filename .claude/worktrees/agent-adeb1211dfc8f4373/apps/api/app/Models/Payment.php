<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'job_offer_id', 'amount_cents', 'currency', 'status',
    'stripe_payment_intent_id', 'stripe_session_id',
])]
class Payment extends Model
{
    protected $table = 'payments';

    protected function casts(): array
    {
        return ['status' => PaymentStatus::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobOffer(): BelongsTo
    {
        return $this->belongsTo(JobOffer::class);
    }
}
