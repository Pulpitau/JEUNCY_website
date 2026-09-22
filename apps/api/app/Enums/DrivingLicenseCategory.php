<?php

namespace App\Enums;

// Categories de permis francais (candidate_profiles.driving_license_categories,
// MOBILE.md §6). Le texte libre driving_license existant est conserve ; la
// reprise vers ces categories passe par candidates:migrate-driving-license
// avec relecture manuelle.
enum DrivingLicenseCategory: string
{
    case AM = 'AM';
    case A1 = 'A1';
    case A2 = 'A2';
    case A = 'A';
    case B1 = 'B1';
    case B = 'B';
    case BE = 'BE';
    case C = 'C';
    case D = 'D';
}
