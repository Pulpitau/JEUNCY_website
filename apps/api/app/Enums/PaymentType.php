<?php

namespace App\Enums;

enum PaymentType: string
{
    case OFFER_PUBLICATION = 'OFFER_PUBLICATION';
    case APPLICATIONS_UNLOCK = 'APPLICATIONS_UNLOCK';
}
