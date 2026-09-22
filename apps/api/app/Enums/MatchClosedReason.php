<?php

namespace App\Enums;

// Pourquoi une ligne offer_interests a ete fermee (closed_reason, MOBILE.md
// §5). La fermeture precede toujours la suppression physique de l'offre ou
// du profil, pour que l'autre partie soit prevenue (MatchClosingService).
enum MatchClosedReason: string
{
    case OFFER_ARCHIVED = 'OFFER_ARCHIVED';
    case OFFER_DELETED = 'OFFER_DELETED';
    case OFFER_EXPIRED = 'OFFER_EXPIRED';
    case APPLICATION_WITHDRAWN = 'APPLICATION_WITHDRAWN';
    case ACCOUNT_DELETED = 'ACCOUNT_DELETED';
}
