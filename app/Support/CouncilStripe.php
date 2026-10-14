<?php

namespace App\Support;

use Stripe\StripeClient;

class CouncilStripe
{
    public function client(): StripeClient
    {
        $secret = Billing::account(config('council.account'))['secret'] ?? null;
        abort_unless($secret, 503, 'Council billing is being configured.');
        $liveKey = str_starts_with($secret, 'sk_live_') || str_starts_with($secret, 'rk_live_');
        abort_unless($liveKey === config('council.livemode') && config('council.catalog.livemode') === config('council.livemode'), 503, 'Council billing environment does not match its catalog.');

        return new StripeClient(['api_key' => $secret, 'stripe_version' => config('council.api_version')]);
    }
}
