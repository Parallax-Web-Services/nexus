<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Support\Billing;
use Inertia\Inertia;

class BillingController extends Controller
{
    /**
     * The billing overview: available plans and active subscriptions,
     * grouped by billing account.
     */
    public function show()
    {
        $entitlements = $this->user->serviceSubscriptions->keyBy('plan_id');

        $plans = Plan::whereNotNull('stripe_price_id')
            ->with('services')
            ->get()
            ->map(function (Plan $plan) use ($entitlements) {
                $subscription = $entitlements->get($plan->id);

                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'pricing' => $plan->pricing,
                    'account' => $plan->billing_account,
                    'account_name' => Billing::account($plan->billing_account)['name'] ?? $plan->billing_account,
                    'services' => $plan->services->pluck('name'),
                    'subscribed' => (bool) $subscription?->isActive(),
                    'expires_at' => $subscription?->expires_at?->toFormattedDateString(),
                ];
            })
            ->groupBy('account_name');

        $portalAccounts = collect($this->user->hasStripeId() ? [Billing::cashierAccount()] : [])
            ->merge($this->user->billingCustomers->pluck('billing_account'))
            ->unique()
            ->values()
            ->map(fn (string $key) => [
                'key' => $key,
                'name' => Billing::account($key)['name'] ?? $key,
            ]);

        return Inertia::render('Billing', [
            'planGroups' => $plans,
            'portalAccounts' => $portalAccounts,
        ]);
    }

    /**
     * Send the user to the Stripe billing portal to manage their plan.
     */
    public function portal(string $account = null)
    {
        $account = $account ?? Billing::cashierAccount();

        abort_unless(Billing::account($account), 404);

        if (Billing::usesCashier($account)) {
            return $this->user->redirectToBillingPortal(route('billing.show'));
        }

        $session = Billing::portalSession($this->user, $account, route('billing.show'));

        return redirect()->away($session->url);
    }

    /**
     * Start a Stripe Checkout session for the given plan.
     */
    public function checkout(Plan $plan)
    {
        abort_unless($plan->stripe_price_id, 404);

        if (Billing::usesCashier($plan->billing_account)) {
            return $this->user
                ->newSubscription('default', $plan->stripe_price_id)
                ->checkout([
                    'success_url' => route('billing.show'),
                    'cancel_url' => route('billing.show'),
                ]);
        }

        $session = Billing::checkoutSession($this->user, $plan, route('billing.show'), route('billing.show'));

        return redirect()->away($session->url);
    }
}
