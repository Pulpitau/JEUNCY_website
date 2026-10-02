<?php

namespace App\Models;

use App\Enums\NewsletterDeliveryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Trace d'envoi de la lettre hebdomadaire : une ligne par edition et par
// destinataire (voir la migration pour le pourquoi).
#[Fillable(['newsletter_edition_id', 'user_id', 'status', 'error', 'sent_at'])]
class NewsletterDelivery extends Model
{
    // Table declaree explicitement : la pluralisation automatique d'Eloquent
    // n'est jamais une source de verite ici (CONVENTIONS.md §7).
    protected $table = 'newsletter_deliveries';

    protected function casts(): array
    {
        return [
            'status' => NewsletterDeliveryStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(NewsletterEdition::class, 'newsletter_edition_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
