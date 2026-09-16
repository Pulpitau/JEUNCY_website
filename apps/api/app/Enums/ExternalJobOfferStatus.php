<?php

namespace App\Enums;

enum ExternalJobOfferStatus: string
{
    // Visible dans la recherche publique.
    case ACTIVE = 'ACTIVE';
    // Ecartee par le filtre (ecole, mandataire, employeur bloque...) et
    // conservee avec sa raison pour pouvoir auditer le filtre.
    case EXCLUDED = 'EXCLUDED';
}
