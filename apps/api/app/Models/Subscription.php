<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'status', 'amount_cents', 'currency', 'is_founder_rate',
    'stripe_subscription_id', 'stripe_customer_id',
    'current_period_end', 'canceled_at',
])]
class Subscription extends Model
{
    protected $table = 'subscriptions';

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'is_founder_rate' => 'boolean',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
