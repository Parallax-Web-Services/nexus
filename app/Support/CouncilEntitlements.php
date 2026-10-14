<?php

namespace App\Support;

use App\Models\CouncilSubscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CouncilEntitlements
{
    public function __construct(protected CouncilCatalog $catalog) {}

    public function sync(array $subscription, int $eventCreated): void
    {
        $customer = $subscription['customer'] ?? null;
        $customerId = is_array($customer) ? ($customer['id'] ?? null) : $customer;
        $user = User::where('stripe_id', $customerId)->first();
        $subscriptionId = $subscription['id'] ?? null;
        if (! $customerId || ! $user || ! $subscriptionId) {
            return;
        }
        $priceIds = collect($subscription['items']['data'] ?? [])->pluck('price.id')->filter()->unique()->values()->all();
        $existing = CouncilSubscription::where('billing_account', config('council.account'))->where('stripe_subscription_id', $subscriptionId)->exists();
        if (! $existing && $this->catalog->servicesForPrices($priceIds) === []) {
            return;
        }
        // Lock the owner, which also serializes simultaneous first deliveries.
        DB::transaction(function () use ($user, $subscriptionId, $customerId, $subscription, $priceIds, $eventCreated) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $row = CouncilSubscription::firstOrNew([
                'billing_account' => config('council.account'),
                'stripe_subscription_id' => $subscriptionId,
            ]);
            if ($row->exists && ($row->user_id !== $user->id || $eventCreated < $row->event_created)) {
                return;
            }
            // Stripe never reactivates a canceled subscription ID.
            if ($row->exists && $row->status === 'canceled' && ($subscription['status'] ?? null) !== 'canceled') {
                return;
            }
            $row->fill([
                'user_id' => $user->id,
                'stripe_customer_id' => $customerId,
                'status' => $subscription['status'] ?? 'incomplete',
                'price_ids' => $priceIds,
                'expires_at' => ! empty($subscription['cancel_at']) ? Carbon::createFromTimestamp($subscription['cancel_at']) : null,
                'event_created' => $eventCreated,
            ])->save();
        });
    }

    public function services(User $user): array
    {
        $prices = CouncilSubscription::where('user_id', $user->id)
            ->where('billing_account', config('council.account'))
            ->whereIn('status', ['active', 'trialing'])
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get()->flatMap(fn ($row) => $row->price_ids)->unique()->all();
        $council = $this->catalog->servicesForPrices($prices);
        $legacy = $user->serviceSubscriptions()->with('plan.services')->get()
            ->filter->isActive()->flatMap(fn ($row) => $row->plan?->services->pluck('key') ?? collect())->all();

        $trial = $user->hasVerifiedEmail() ? \App\Models\CouncilTrial::where('user_id', $user->id)->where('expires_at', '>', now())->first() : null;

        return array_values(array_unique(array_merge($council, $legacy, $trial?->services ?? [])));
    }
}
