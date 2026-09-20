<?php

return [
    /*
     * No provider is enabled by default.
     *
     * A provider must only be enabled after a real provider adapter,
     * credentials and verified webhook implementation exist.
     */
    'provider' => env('BILLING_PROVIDER'),

    'checkout_enabled' => filter_var(
        env('BILLING_CHECKOUT_ENABLED', false),
        FILTER_VALIDATE_BOOL,
    ),

    'paddle' => [
        'environment' => env('PADDLE_ENVIRONMENT', 'sandbox'),

        'api_key' => env('PADDLE_API_KEY'),

        'client_token' => env('PADDLE_CLIENT_TOKEN'),

        'webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),

        'prices' => [
            'professional' => env(
                'PADDLE_PRICE_PROFESSIONAL'
            ),
            'team' => env(
                'PADDLE_PRICE_TEAM'
            ),
        ],
    ],

    'polar' => [
        'environment' => env('POLAR_ENVIRONMENT', 'production'),

        'access_token' => env('POLAR_ACCESS_TOKEN'),

        'webhook_secret' => env('POLAR_WEBHOOK_SECRET'),

        'products' => [
            'professional' => env(
                'POLAR_PRODUCT_PROFESSIONAL'
            ),
            'team' => env(
                'POLAR_PRODUCT_TEAM'
            ),
        ],
    ],

    'frontend_url' => rtrim(
        (string) env(
            'FRONTEND_URL',
            'http://127.0.0.1:3000'
        ),
        '/'
    ),
];
