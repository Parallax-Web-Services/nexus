<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Support\Billing;
use Illuminate\Console\Command;
use Laravel\Cashier\Cashier;

class SyncPlansFromStripe extends Command
{
    /**
     * @var string
     */
    protected $signature = 'nexus:sync-plans {account? : Billing account to sync (default: every account with a configured secret)}';

    /**
     * @var string
     */
    protected $description = 'Sync active recurring Stripe prices into Nexus plans (Stripe is the source of truth)';

    public function handle(): int
    {
        if ($only = $this->argument('account')) {
            if (! Billing::account($only)) {
                $this->error("Unknown billing account [{$only}]. Configured: ".implode(', ', array_keys(Billing::accounts())));

                return self::FAILURE;
            }

            if (! (Billing::account($only)['secret'] ?? null)) {
                $this->error("No Stripe secret configured for billing account [{$only}].");

                return self::FAILURE;
            }

            $accounts = [$only];
        } else {
            $accounts = collect(Billing::accounts())
                ->filter(fn ($config) => $config['secret'] ?? null)
                ->keys()
                ->all();

            if (empty($accounts)) {
                $this->error('No billing account has a Stripe secret configured.');

                return self::FAILURE;
            }
        }

        foreach ($accounts as $account) {
            try {
                $this->syncAccount($account);
            } catch (\Stripe\Exception\ApiErrorException $e) {
                $this->error("Stripe error while syncing [{$account}]: {$e->getMessage()}");
            }
        }

        Plan::whereNull('stripe_price_id')
            ->each(function (Plan $plan) {
                $this->warn("Unmapped: \"{$plan->name}\" has no Stripe price and cannot be checked out.");
            });

        return self::SUCCESS;
    }

    protected function syncAccount(string $account): void
    {
        $this->info("Syncing [{$account}]...");

        $stripe = Billing::usesCashier($account) ? Cashier::stripe() : Billing::client($account);

        $prices = $stripe->prices->all([
            'active' => true,
            'type' => 'recurring',
            'expand' => ['data.product'],
            'limit' => 100,
        ]);

        $seen = [];

        foreach ($prices->autoPagingIterator() as $price) {
            $product = $price->product;

            if (! $product || ($product->deleted ?? false) || ! $product->active) {
                continue;
            }

            $plan = Plan::updateOrCreate(
                ['billing_account' => $account, 'stripe_price_id' => $price->id],
                [
                    'name' => $this->planName($product, $price),
                    'pricing' => $this->pricingLabel($price),
                ]
            );

            $seen[] = $price->id;

            $this->line(sprintf(
                '%s %s (%s, %s)',
                $plan->wasRecentlyCreated ? 'Created' : 'Synced ',
                $plan->name,
                $price->id,
                $plan->pricing
            ));
        }

        $this->info(count($seen)." plan(s) synced for [{$account}].");

        Plan::where('billing_account', $account)
            ->whereNotNull('stripe_price_id')
            ->whereNotIn('stripe_price_id', $seen)
            ->each(function (Plan $plan) use ($account) {
                $this->warn("Stale: \"{$plan->name}\" maps to {$plan->stripe_price_id}, which is no longer an active price on [{$account}].");
            });
    }

    protected function planName(object $product, object $price): string
    {
        if ($price->nickname) {
            return "{$product->name} – {$price->nickname}";
        }

        return "{$product->name} ({$this->intervalLabel($price)})";
    }

    protected function pricingLabel(object $price): string
    {
        $interval = $price->recurring->interval ?? 'month';
        $count = $price->recurring->interval_count ?? 1;
        $per = $count > 1 ? "every {$count} {$interval}s" : $interval;

        if ($price->unit_amount === null) {
            return "metered / {$per}";
        }

        return Cashier::formatAmount($price->unit_amount, $price->currency)." / {$per}";
    }

    protected function intervalLabel(object $price): string
    {
        $interval = $price->recurring->interval ?? 'month';
        $count = $price->recurring->interval_count ?? 1;

        if ($count > 1) {
            return "every {$count} {$interval}s";
        }

        return match ($interval) {
            'day' => 'daily',
            'week' => 'weekly',
            'month' => 'monthly',
            'year' => 'yearly',
            default => $interval,
        };
    }
}
