<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [],

    'allowed_origins_patterns' => [
        '#^https://crypticxlab\\.duckdns\\.org$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => false,

];
