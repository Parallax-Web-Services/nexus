<?php

namespace App\Listeners;

use App\Support\Billing;
use App\Support\EntitlementSync;
use Laravel\Cashier\Events\WebhookHandled;

/**
 * Bridges Cashier's webhook (the primary billing account) into the shared
 * entitlement sync. Non-Cashier accounts are handled by StripeWebhookController.
 */
class SyncServiceSubscriptions
{
    public function __construct(protected EntitlementSync $sync)
    {
    }

    public function handle(WebhookHandled $event): void
    {
        $type = $event->payload['type'] ?? null;

        if (! in_array($type, EntitlementSync::SUBSCRIPTION_EVENTS)) {
            return;
        }

        $this->sync->sync(
            Billing::cashierAccount(),
            $event->payload['data']['object'] ?? [],
            $type === 'customer.subscription.deleted'
        );
    }
}
