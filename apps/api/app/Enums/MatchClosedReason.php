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

    /**
     * Personne n'a repondu dans le delai (relances, MOBILE.md §5).
     *
     * Distincte de OFFER_EXPIRED : l'offre est toujours en ligne, c'est la
     * mise en relation qui s'est eteinte faute de geste.
     */
    case EXPIRED = 'EXPIRED';

    /**
     * Cloture par l'equipe apres trente jours de silence de l'employeur.
     *
     * Jeuncy ferme la ligne et le dit au candidat ; il ne pose JAMAIS de
     * statut de candidature au nom de l'entreprise. « On n'a pas eu de
     * reponse » est une chose, « vous etes refuse » en est une autre, et
     * seule l'entreprise peut dire la seconde.
     */
    case CLOSED_BY_STAFF = 'CLOSED_BY_STAFF';
}
