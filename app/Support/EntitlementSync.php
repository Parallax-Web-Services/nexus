<?php

namespace App\Support;

use App\Models\BillingCustomer;
use App\Models\Plan;
use App\Models\ServiceSubscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Cashier;

/**
 * Translates Stripe subscription state into Nexus service entitlements:
 * one ServiceSubscription row per plan, which the network apps read for
 * access. Shared by the Cashier webhook listener (primary account) and the
 * per-account webhook controller (all other billing accounts).
 */
class EntitlementSync
{
    public const SUBSCRIPTION_EVENTS = [
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
    ];

    public function sync(string $account, array $stripeSubscription, bool $deleted = false): void
    {
        // Council subscriptions have their own per-subscription projection.
        if (($stripeSubscription['metadata']['type'] ?? null) === 'council') {
            return;
        }

        $user = $this->resolveUser($account, $stripeSubscription['customer'] ?? null);

        if (! $user) {
            return;
        }

        $priceIds = collect($stripeSubscription['items']['data'] ?? [])
            ->pluck('price.id')
            ->filter();

        $plans = Plan::where('billing_account', $account)
            ->whereIn('stripe_price_id', $priceIds)
            ->get();

        $active = ! $deleted
            && in_array($stripeSubscription['status'] ?? null, ['active', 'trialing']);

        foreach ($plans as $plan) {
            $subscription = ServiceSubscription::firstOrNew([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
            ]);

            $subscription->expires_at = match (true) {
                ! $active => now(),
                // Canceled at period end: entitlement lasts until Stripe ends it.
                ! empty($stripeSubscription['cancel_at']) => Carbon::createFromTimestamp($stripeSubscription['cancel_at']),
                default => null,
            };

            $subscription->save();
        }
    }

    protected function resolveUser(string $account, ?string $customerId): ?User
    {
        if (! $customerId) {
            return null;
        }

        if (Billing::usesCashier($account)) {
            return Cashier::findBillable($customerId);
        }

        return BillingCustomer::where('billing_account', $account)
            ->where('stripe_customer_id', $customerId)
            ->first()?->user;
    }
}
