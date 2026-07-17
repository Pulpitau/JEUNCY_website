<?php

namespace App\Enums;

enum VideoRoomStatus: string
{
    case SCHEDULED = 'SCHEDULED';
    case LIVE = 'LIVE';
    case ENDED = 'ENDED';
}
