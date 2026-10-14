<?php

namespace App\Support;

use App\Models\BillingCustomer;
use App\Models\Plan;
use App\Models\User;
use Stripe\StripeClient;

/**
 * Multi-account Stripe access. The account flagged `cashier` in
 * config/billing.php is handled by Laravel Cashier; every other account is
 * driven directly through the Stripe SDK via this class.
 */
class Billing
{
    /**
     * All configured accounts, keyed by account slug.
     */
    public static function accounts(): array
    {
        return config('billing.accounts', []);
    }

    public static function account(string $key): ?array
    {
        return config("billing.accounts.{$key}");
    }

    /**
     * Whether this account is the Cashier-managed (primary) account.
     */
    public static function usesCashier(string $key): bool
    {
        return (bool) (static::account($key)['cashier'] ?? false);
    }

    public static function cashierAccount(): string
    {
        foreach (static::accounts() as $key => $account) {
            if ($account['cashier'] ?? false) {
                return $key;
            }
        }

        return 'parallax';
    }

    public static function client(string $key): StripeClient
    {
        $secret = static::account($key)['secret'] ?? null;

        if (! $secret) {
            throw new \RuntimeException("No Stripe secret configured for billing account [{$key}].");
        }

        return new StripeClient($secret);
    }

    /**
     * The user's Stripe customer on the given (non-Cashier) account,
     * created on Stripe if they don't have one yet.
     */
    public static function customerFor(User $user, string $account): BillingCustomer
    {
        $customer = BillingCustomer::where('user_id', $user->id)
            ->where('billing_account', $account)
            ->first();

        if ($customer) {
            return $customer;
        }

        $stripeCustomer = static::client($account)->customers->create([
            'email' => $user->email,
            'name' => $user->name,
        ]);

        return BillingCustomer::create([
            'user_id' => $user->id,
            'billing_account' => $account,
            'stripe_customer_id' => $stripeCustomer->id,
        ]);
    }

    /**
     * Start a subscription Checkout session for a plan on a non-Cashier account.
     */
    public static function checkoutSession(User $user, Plan $plan, string $successUrl, string $cancelUrl)
    {
        $customer = static::customerFor($user, $plan->billing_account);

        return static::client($plan->billing_account)->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customer->stripe_customer_id,
            'line_items' => [
                ['price' => $plan->stripe_price_id, 'quantity' => 1],
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);
    }

    /**
     * Start a billing-portal session on a non-Cashier account.
     */
    public static function portalSession(User $user, string $account, string $returnUrl)
    {
        $customer = static::customerFor($user, $account);

        return static::client($account)->billingPortal->sessions->create([
            'customer' => $customer->stripe_customer_id,
            'return_url' => $returnUrl,
        ]);
    }
}
