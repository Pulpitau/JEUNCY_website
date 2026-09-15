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
    // Idem TRIAL mais pour une offre publiee gratuitement grace a un
    // abonnement actif (voir JobOfferService::publishViaSubscriptionForUser) :
    // aucune transaction Stripe propre a cette offre, le paiement reel est
    // celui de l'abonnement (voir Subscription).
    case SUBSCRIPTION = 'SUBSCRIPTION';
    // Offre publiee gratuitement depuis que Jeuncy ne facture plus les
    // entreprises (voir config services.jeuncy.gratuit et
    // JobOfferService::publishFreeForUser). Pas d'echeance, pas d'essai,
    // aucun paiement derriere.
    case FREE = 'FREE';
}
