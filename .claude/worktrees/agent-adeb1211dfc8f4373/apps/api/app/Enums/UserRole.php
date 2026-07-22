<?php

namespace App\Enums;

enum UserRole: string
{
    case CANDIDATE = 'CANDIDATE';
    case COMPANY = 'COMPANY';
    case CFA = 'CFA';
    case ADMIN = 'ADMIN';
}
