<?php

return [
    // Ako je true, GPS podaci se ne čuvaju (privacy by default)
    'strip_gps' => env('PHOTOS_STRIP_GPS', true),

    // Generisane širine varijanti (ključevi ulaze u p->urls['sm'|'md'|'lg'])
    'variants' => [
        'sm' => 320,
        'md' => 800,
        'lg' => 1600,
    ],

    // Dodatno WebP uz JPG
    'make_webp' => env('PHOTOS_MAKE_WEBP', true),
];
