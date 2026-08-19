<?php

namespace App\Enums;

enum CompensationPeriod: string
{
    case HOURLY = 'HOURLY';
    case MONTHLY = 'MONTHLY';
    case YEARLY = 'YEARLY';

    // Suffixe d'affichage, cote serveur comme cote client (voir
    // formatCompensation dans apps/web) : un candidat doit lire « 1 200 € brut
    // / mois » et jamais « 1200 » tout seul, qui ne veut rien dire.
    public function suffix(): string
    {
        return match ($this) {
            self::HOURLY => '/ heure',
            self::MONTHLY => '/ mois',
            self::YEARLY => '/ an',
        };
    }
}
