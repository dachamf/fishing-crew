<?php

return [
    // sm/md/lg ključevi – vrednosti su max širine u px
    'variants' => [
        'sm' => 320,
        'md' => 800,
        'lg' => 1600,
    ],

    // generiši i .webp varijante
    'make_webp' => env('PHOTOS_MAKE_WEBP', true),

    // iz EXIF-a ne čuvaj GPS (privatnost)
    'strip_gps' => env('PHOTOS_STRIP_GPS', true),
];
