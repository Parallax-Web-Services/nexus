<?php

// Additional Stripe accounts, comma-separated as key:Name in BILLING_ACCOUNTS,
// for example "acme:Acme Inc". Each account reads <KEY>_STRIPE_SECRET and
// <KEY>_STRIPE_WEBHOOK_SECRET, where <KEY> is the key in upper snake case.
$additionalAccounts = [];

foreach (array_filter(array_map('trim', explode(',', (string) env('BILLING_ACCOUNTS', '')))) as $entry) {
    [$key, $name] = array_pad(array_map('trim', explode(':', $entry, 2)), 2, null);
    $prefix = strtoupper(str_replace('-', '_', $key));

    $additionalAccounts[$key] = [
        'name' => $name ?: $key,
        'secret' => env($prefix.'_STRIPE_SECRET'),
        'webhook_secret' => env($prefix.'_STRIPE_WEBHOOK_SECRET'),
    ];
}

return [

    /*
    |--------------------------------------------------------------------------
    | Billing Accounts
    |--------------------------------------------------------------------------
    |
    | Nexus can bill through multiple Stripe accounts. The account flagged
    | `cashier` is the primary account and is handled by Laravel
    | Cashier, including its webhook at /stripe/webhook. Every other account
    | is driven directly through the Stripe SDK: checkout and billing-portal
    | sessions are created per account, customers are tracked in the
    | billing_customers table, and webhooks arrive at
    | /stripe/webhook/{account} signed with that account's webhook secret.
    |
    */

    'accounts' => [

        'parallax' => [
            'name' => env('BILLING_PRIMARY_NAME', env('APP_NAME', 'Nexus')),
            'cashier' => true,
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],

    ] + $additionalAccounts,

];
