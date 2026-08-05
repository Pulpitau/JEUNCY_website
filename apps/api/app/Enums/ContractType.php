<?php

namespace App\Enums;

enum ContractType: string
{
    case ALTERNANCE = 'ALTERNANCE';
    case SAISONNIER = 'SAISONNIER';
    case BENEVOLAT = 'BENEVOLAT';
    case JOB_ETUDIANT = 'JOB_ETUDIANT';
    case STAGE = 'STAGE';
}
