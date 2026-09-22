<?php

namespace App\Enums;

// Geste d'une partie sur un couple candidat / offre Jeuncy (offer_interests,
// MOBILE.md §5). Null en base = pas encore decide. Une decision est finale
// hors fenetre d'annulation : LIKE repete idempotent, decision contraire
// refusee (INTEREST_ALREADY_DECIDED).
enum InterestDecision: string
{
    case LIKE = 'LIKE';
    case PASS = 'PASS';
}
