<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Support\Billing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CouncilReadiness extends Command
{
    protected $signature = 'council:readiness';

    protected $description = 'Report council launch configuration without exposing credentials or contacting Stripe';

    public function handle(): int
    {
        $secret = Billing::account(config('council.account'))['secret'] ?? '';
        $live = str_starts_with($secret, 'sk_live_') || str_starts_with($secret, 'rk_live_');
        $checks = [
            'Public Nexus URL' => in_array(config('app.url'), config('council.public_urls'), true),
            'Live catalog selected' => config('council.livemode') && config('council.catalog.livemode'),
            'Live Stripe key configured' => $secret && $live,
            'Council webhook signing secret' => (bool) config('council.webhook_secret'),
            'Council billing portal configured' => (bool) config('council.portal_configuration'),
            'Council database migrations' => Schema::hasTable('council_proposals') && Schema::hasTable('council_votes') && Schema::hasTable('council_subscriptions'),
            'Twelve council plans synced' => Schema::hasTable('plans') && Plan::where('billing_account', config('council.account'))->whereIn('stripe_price_id', collect(config('council.catalog.plans'))->flatMap(fn ($plan) => collect($plan['prices'])->pluck('id'))->all())->count() === 12,
        ];
        foreach ($checks as $name => $ready) {
            $this->line(($ready ? 'READY   ' : 'MISSING ').$name);
        }
        $this->line('OAuth credentials: register separate continuum and boardroom clients in this deployment and install their private exports in each app.');
        $this->line('Stripe Tax: automatic collection is off; verify business settings and registrations before configuring it.');
        $this->line('This check does not confirm public DNS, callback reachability, key permissions or signing-secret validity.');

        return in_array(false, array_map(fn ($value) => (bool) $value, $checks), true) ? self::FAILURE : self::SUCCESS;
    }
}
