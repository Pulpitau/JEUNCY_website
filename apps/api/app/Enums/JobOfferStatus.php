<?php

namespace App\Enums;

enum JobOfferStatus: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case EXPIRED = 'EXPIRED';
    case ARCHIVED = 'ARCHIVED';
}
