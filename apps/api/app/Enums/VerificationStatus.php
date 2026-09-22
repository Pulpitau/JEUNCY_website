<?php

namespace App\Enums;

// Etat de verification d'une organisation (MOBILE.md §4.0). Une fiche nait
// PENDING ; seule une organisation VERIFIED voit des candidats (decks,
// interets, CVtheque, candidatures recues). Jamais VERIFIED par defaut :
// un registre des entreprises muet laisse la fiche PENDING.
enum VerificationStatus: string
{
    case PENDING = 'PENDING';
    case VERIFIED = 'VERIFIED';
    case REJECTED = 'REJECTED';
}
