<?php

namespace App\Enums;

enum NotificationType: string
{
    case NEW_APPLICATION = 'NEW_APPLICATION';
    case APPLICATION_STATUS_CHANGED = 'APPLICATION_STATUS_CHANGED';
    case PAYMENT_SUCCEEDED = 'PAYMENT_SUCCEEDED';
    case VIDEO_ROOM_INVITE = 'VIDEO_ROOM_INVITE';
    case JOB_OFFER_EXPIRING = 'JOB_OFFER_EXPIRING';
    case TRIAL_OFFERS_ARCHIVED = 'TRIAL_OFFERS_ARCHIVED';
    case VIDEO_ROOM_REMINDER = 'VIDEO_ROOM_REMINDER';
    case PAYMENT_REFUNDED = 'PAYMENT_REFUNDED';

    // Une offre publiee correspond au profil d un candidat : il en est
    // prevenu pour pouvoir postuler lui-meme (voir JobOfferMatchService).
    case JOB_OFFER_MATCH = 'JOB_OFFER_MATCH';
}
