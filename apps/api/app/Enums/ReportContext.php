<?php

namespace App\Enums;

// D'ou part un signalement (reports.context, MOBILE.md §7) : une carte du
// deck, un match, une photo d'organisation ou une offre. Pas de pendant TS :
// seule l'app mobile (lot 2) le consomme, l'admin des signalements vient
// plus tard.
enum ReportContext: string
{
    case CARD = 'CARD';
    case MATCH = 'MATCH';
    case PHOTO = 'PHOTO';
    case OFFER = 'OFFER';
}
