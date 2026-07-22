<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case SENT = 'SENT';
    case SEEN = 'SEEN';
    case INTERVIEW = 'INTERVIEW';
    case ACCEPTED = 'ACCEPTED';
    case REJECTED = 'REJECTED';
}
