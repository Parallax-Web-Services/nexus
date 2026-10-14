<?php

return [
    'services' => [
        'positronic' => [
            'domain' => env('DOMAIN_POSITRONIC'),
            'url' => env('URL_POSITRONIC'),
        ],

        'plexus' => [
            'domain' => env('DOMAIN_PLEXUS'),
            'url' => env('URL_PLEXUS'),
        ],

        'perpetuity' => [
            'domain' => env('DOMAIN_PERPETUITY'),
            'url' => env('URL_PERPETUITY'),
        ],

        'slipstream' => [
            'domain' => env('DOMAIN_SLIPSTREAM'),
            'url' => env('URL_SLIPSTREAM'),
        ],

        'talos' => [
            'domain' => env('DOMAIN_TALOS'),
            'url' => env('URL_TALOS'),
        ],
    ]
];
