<?php

namespace App\Enums;

enum PaymentType: string
{
    case OFFER_PUBLICATION = 'OFFER_PUBLICATION';
    case APPLICATIONS_UNLOCK = 'APPLICATIONS_UNLOCK';
    // Prelevement mensuel d'abonnement, enregistre depuis la facture
    // Stripe (voir SubscriptionService::handleInvoicePaid).
    case SUBSCRIPTION = 'SUBSCRIPTION';
}
