<?php

namespace App\Console\Commands;

use App\Support\CouncilStripe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class RegisterCouncilWebhook extends Command
{
    protected $signature = 'council:register-webhook';

    protected $description = 'Verify deployed Nexus and register its council webhook; save the signing secret privately';

    private const EVENTS = ['customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted', 'invoice.paid', 'invoice.payment_failed', 'checkout.session.completed', 'checkout.session.async_payment_succeeded'];

    public function handle(CouncilStripe $stripe): int
    {
        $base = rtrim(config('app.url'), '/');
        if (! in_array($base, config('council.public_urls'), true)) {
            $this->error('Live council webhooks require an approved public HTTPS Nexus URL.');

            return self::FAILURE;
        }
        $directory = storage_path('app/private/council');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        if (! is_writable($directory)) {
            $this->error('Private signing-secret directory must be writable.');

            return self::FAILURE;
        }
        try {
            $health = Http::acceptJson()->timeout(10)->get($base.'/council/health');
            if (! $health->successful() || $health->json('service') !== 'parallax-nexus-council'
                || $health->json('catalog_livemode') !== true || $health->json('billing_livemode') !== true) {
                $this->error('The public Nexus council endpoint must be reachable and configured for the live catalog first.');

                return self::FAILURE;
            }
            $client = $stripe->client();
            $account = $client->accounts->retrieve();
            if ($account->id !== config('council.stripe_account_id') || ! config('council.livemode')) {
                $this->error('The Stripe account must match the approved live Parallax catalog.');

                return self::FAILURE;
            }
            foreach ($client->webhookEndpoints->all(['limit' => 100])->autoPagingIterator() as $existing) {
                if ($existing->url === $base.'/stripe/council/webhook') {
                    if (! config('council.webhook_secret')) {
                        $this->error('The endpoint exists. Supply its signing secret from the Stripe Dashboard; it cannot be retrieved.');

                        return self::FAILURE;
                    }
                    $client->webhookEndpoints->update($existing->id, ['enabled_events' => self::EVENTS, 'disabled' => false]);
                    $this->info('Existing council webhook configured.');

                    return self::SUCCESS;
                }
            }
            $endpoint = $client->webhookEndpoints->create(['url' => $base.'/stripe/council/webhook', 'api_version' => config('council.api_version'), 'enabled_events' => self::EVENTS, 'metadata' => ['catalog' => 'parallax_council']]);
            $path = $directory.'/stripe-webhook.json';
            file_put_contents($path, json_encode(['id' => $endpoint->id, 'secret' => $endpoint->secret], JSON_THROW_ON_ERROR), LOCK_EX);
            chmod($path, 0600);
            $this->info('Webhook registered. Signing secret saved privately; rebuild the configuration cache.');

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Webhook registration could not complete. Check public Nexus availability and private Stripe credentials.');

            return self::FAILURE;
        }
    }
}
