<?php

$privateWebhook = __DIR__.'/../storage/app/private/council/stripe-webhook.json';
$webhook = is_file($privateWebhook) ? json_decode(file_get_contents($privateWebhook), true) : [];

return [
    'account' => 'parallax',
    'stripe_account_id' => env('COUNCIL_STRIPE_ACCOUNT_ID'),
    'api_version' => '2026-08-26.dahlia',
    'livemode' => (bool) env('COUNCIL_LIVEMODE', false),
    'webhook_secret' => env('COUNCIL_STRIPE_WEBHOOK_SECRET', $webhook['secret'] ?? null),
    'clients' => [
        'quark' => env('COUNCIL_QUARK_CALLBACK'),
        'pulsar' => env('COUNCIL_PULSAR_CALLBACK'),
        'continuum' => env('COUNCIL_CONTINUUM_CALLBACK'),
        'boardroom' => env('COUNCIL_BOARDROOM_CALLBACK'),
    ],
    'portal_configuration' => env('COUNCIL_STRIPE_PORTAL_CONFIGURATION'),
    // Public HTTPS Nexus URLs approved for live council webhooks, comma-separated.
    'public_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('COUNCIL_PUBLIC_URLS', ''))))),
    'catalog' => json_decode(file_get_contents(__DIR__.'/../resources/council-catalog.json'), true),
];
