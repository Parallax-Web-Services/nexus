<?php

namespace App\Http\Controllers;

use App\Support\Billing;
use App\Support\EntitlementSync;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Webhook endpoint for non-Cashier billing accounts (/stripe/webhook/{account}).
 * The Cashier-managed account keeps using Cashier's own /stripe/webhook.
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request, string $account, EntitlementSync $sync)
    {
        $config = Billing::account($account);

        abort_if(! $config || ($config['cashier'] ?? false), 404);
        abort_unless($config['webhook_secret'] ?? null, 400, 'Webhook secret not configured.');

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                $config['webhook_secret']
            );
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            abort(403, 'Invalid webhook signature.');
        }

        if (in_array($event->type, EntitlementSync::SUBSCRIPTION_EVENTS)) {
            $sync->sync(
                $account,
                $event->data->object->toArray(),
                $event->type === 'customer.subscription.deleted'
            );
        }

        return response('Webhook handled');
    }
}
