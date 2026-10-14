<?php

namespace App\Http\Controllers;

use App\Support\CouncilEntitlements;
use App\Support\CouncilStripe;
use Illuminate\Http\Request;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class CouncilWebhookController extends Controller
{
    public function handle(Request $request, CouncilStripe $stripe, CouncilEntitlements $entitlements)
    {
        $secret = config('council.webhook_secret');
        abort_unless($secret, 503, 'Council webhook is not configured.');
        try {
            $event = Webhook::constructEvent($request->getContent(), $request->header('Stripe-Signature', ''), $secret);
        } catch (SignatureVerificationException|\UnexpectedValueException) {
            abort(400, 'Invalid Stripe signature.');
        }
        abort_unless((bool) $event->livemode === config('council.livemode'), 400, 'Unexpected billing environment.');
        $object = $event->data->object->toArray();
        $subscriptionId = match ($event->type) {
            'customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted' => $object['id'] ?? null,
            'invoice.paid', 'invoice.payment_failed' => $object['parent']['subscription_details']['subscription'] ?? $object['subscription'] ?? null,
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => in_array($object['payment_status'] ?? '', ['paid', 'no_payment_required'], true) ? ($object['subscription'] ?? null) : null,
            default => null,
        };
        if (is_array($subscriptionId)) {
            $subscriptionId = $subscriptionId['id'] ?? null;
        }
        if ($subscriptionId) {
            // Fetch current truth, including on deletion: delayed events cannot revive access.
            try {
                $subscription = $stripe->client()->subscriptions->retrieve($subscriptionId, []);
            } catch (ApiErrorException) {
                // A non-2xx response makes Stripe retry; never echo provider credentials.
                abort(503, 'Council synchronization is temporarily unavailable.');
            }
            $entitlements->sync($subscription->toArray(), (int) $event->created);
        }

        return response('Council webhook handled');
    }
}
