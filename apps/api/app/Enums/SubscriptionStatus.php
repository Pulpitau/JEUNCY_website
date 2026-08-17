<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case ACTIVE = 'ACTIVE';
    case CANCELED = 'CANCELED';
    // Echeance impayee (carte expiree, fonds insuffisants...) : gardee
    // distincte de CANCELED, Stripe retente le paiement automatiquement
    // avant de finir par annuler (customer.subscription.deleted).
    case PAST_DUE = 'PAST_DUE';
}
