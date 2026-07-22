<?php

return [
    'secret' => env('JWT_SECRET'),
    'ttl_minutes' => (int) env('JWT_TTL_MINUTES', 15),
    'refresh_secret' => env('JWT_REFRESH_SECRET'),
    'refresh_ttl_minutes' => (int) env('JWT_REFRESH_TTL_MINUTES', 10080),
];
