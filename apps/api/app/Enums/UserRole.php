<?php

namespace App\Enums;

enum UserRole: string
{
    case CANDIDATE = 'CANDIDATE';
    case COMPANY = 'COMPANY';
    case CFA = 'CFA';
    // Membre de l'equipe Jeuncy : consulte la CVtheque comme un client
    // abonne, sans aucun pouvoir d'administration.
    case STAFF = 'STAFF';
    case ADMIN = 'ADMIN';
}
