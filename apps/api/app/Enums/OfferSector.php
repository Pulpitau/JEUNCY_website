<?php

namespace App\Enums;

// Secteurs d'activite d'une offre (liste fermee, MOBILE.md §3.1) : le
// candidat en choisit 1 a 3 dans « ce que je cherche », l'employeur en
// donne un par offre. Pendant TS : packages/shared/src/enums/offer-sector.ts.
enum OfferSector: string
{
    case COMMERCE = 'COMMERCE';
    case RESTAURATION_HOTELLERIE = 'RESTAURATION_HOTELLERIE';
    case BTP = 'BTP';
    case INDUSTRIE = 'INDUSTRIE';
    case LOGISTIQUE_TRANSPORT = 'LOGISTIQUE_TRANSPORT';
    case SANTE_SOCIAL = 'SANTE_SOCIAL';
    case INFORMATIQUE_NUMERIQUE = 'INFORMATIQUE_NUMERIQUE';
    case ADMINISTRATIF_GESTION = 'ADMINISTRATIF_GESTION';
    case BANQUE_ASSURANCE = 'BANQUE_ASSURANCE';
    case COMMUNICATION_MARKETING = 'COMMUNICATION_MARKETING';
    case AGRICULTURE_ENVIRONNEMENT = 'AGRICULTURE_ENVIRONNEMENT';
    case ART_CULTURE = 'ART_CULTURE';
    case EDUCATION_FORMATION = 'EDUCATION_FORMATION';
    case BEAUTE_BIEN_ETRE = 'BEAUTE_BIEN_ETRE';
    case SPORT_ANIMATION = 'SPORT_ANIMATION';
    case AUTRE = 'AUTRE';
}
