<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case SUCCEEDED = 'SUCCEEDED';
    case FAILED = 'FAILED';
    case REFUNDED = 'REFUNDED';
    // Utilise uniquement sur JobOffer::payment_status (jamais Payment::status,
    // aucune transaction Stripe reelle n'existe pour une offre publiee via
    // l'essai gratuit) : voir JobOfferService::publishViaTrialForUser.
    case TRIAL = 'TRIAL';
}
