<?php

namespace App\Models;

use App\Enums\NewsletterEditionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Une edition de la lettre hebdomadaire : son texte et son etat.
//
// `status` n'est PAS fillable : il ne se pose que par NewsletterService
// (depot, armement, fin de passe). Un statut acceptable en mass-assignment
// aurait permis de deposer directement une edition « PRETE », c'est-a-dire
// d'armer l'envoi du lundi par un formulaire sans jamais passer par le geste
// qui l'arme.
#[Fillable(['slug', 'subject', 'html', 'text'])]
class NewsletterEdition extends Model
{
    use HasFactory;

    protected $table = 'newsletter_editions';

    protected function casts(): array
    {
        return [
            'status' => NewsletterEditionStatus::class,
            'prepared_at' => 'datetime',
            'sent_at' => 'datetime',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NewsletterDelivery::class);
    }

    /** Une edition partie ne repart jamais, par aucun chemin. */
    public function estEnvoyee(): bool
    {
        return $this->status === NewsletterEditionStatus::ENVOYEE;
    }
}
