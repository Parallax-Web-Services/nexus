<?php

namespace App\Http\Controllers;

use App\Models\CouncilSubscription;
use App\Support\Billing;
use App\Support\CouncilCatalog;
use App\Support\CouncilStripe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Stripe\Exception\ApiErrorException;

class CouncilCheckoutController extends Controller
{
    private function selection(Request $request, CouncilCatalog $catalog): array
    {
        $input = $request->validate([
            'plan' => ['sometimes', 'string'],
            'interval' => ['sometimes', 'in:monthly,annual'],
            'executives' => ['sometimes', 'array', 'max:6'],
            'executives.*' => ['string', 'distinct'],
            'price' => ['prohibited'], 'price_id' => ['prohibited'], 'amount' => ['prohibited'],
        ]);

        return $catalog->selection($input);
    }

    public function show(Request $request, CouncilCatalog $catalog)
    {
        $selection = $this->selection($request, $catalog);
        if (! $request->user()) {
            $request->session()->put('url.intended', $request->fullUrl());
        }
        $secret = Billing::account(config('council.account'))['secret'] ?? '';
        $keyIsLive = str_starts_with($secret, 'rk_live_') || str_starts_with($secret, 'sk_live_');
        $ready = $secret && config('council.webhook_secret') && $keyIsLive === config('council.livemode') && config('council.catalog.livemode') === config('council.livemode') && collect($selection['line_items'])->every(fn ($item) => ! empty($item['price']));

        if ($request->user()) {
            return Inertia::render('CouncilCheckout', [
                'selection' => $selection,
                'executiveNames' => array_map(fn ($key) => $catalog->executiveName($key), $selection['executives']),
                'ready' => (bool) $ready,
                'checkoutState' => $request->query('checkout'),
                'csrfToken' => csrf_token(),
            ]);
        }

        return view('council-checkout', compact('selection', 'ready'));
    }

    public function checkout(Request $request, CouncilCatalog $catalog, CouncilStripe $stripe)
    {
        $selection = $this->selection($request, $catalog);
        abort_unless(config('council.webhook_secret'), 503, 'Council payment confirmation is being configured.');
        abort_unless(collect($selection['line_items'])->every(fn ($item) => ! empty($item['price'])), 503, 'Council prices are being configured.');
        $user = $request->user();
        try {
            $url = Cache::lock('council-checkout:'.$user->id, 60)->block(5, function () use ($request, $user, $selection, $stripe) {
                $user->refresh();
                $client = $stripe->client();
                // One council per identity. Changes to an existing council happen in the portal.
                if (CouncilSubscription::where('user_id', $user->id)->where('billing_account', config('council.account'))->whereIn('status', ['active', 'trialing', 'past_due', 'unpaid', 'incomplete', 'paused'])->exists()) {
                    return $stripe->client()->billingPortal->sessions->create([
                        'customer' => $user->stripe_id, 'return_url' => route('council.checkout'), 'configuration' => config('council.portal_configuration'),
                    ])->url;
                }
                $fingerprint = hash('sha256', json_encode($selection['line_items']));
                $cacheKey = 'council-checkout-session:'.$user->id;
                $cached = Cache::get($cacheKey);
                if (! $user->stripe_id) {
                    $customer = $client->customers->create(['name' => $user->name, 'email' => $user->email], ['idempotency_key' => 'council-customer-'.$user->id]);
                    $user->forceFill(['stripe_id' => $customer->id])->save();
                }
                // Also detect subscriptions whose webhook has not reached Nexus yet.
                foreach ($client->subscriptions->all(['customer' => $user->stripe_id, 'status' => 'all', 'limit' => 100])->autoPagingIterator() as $subscription) {
                    if (($subscription->metadata->type ?? null) === 'council' && ! in_array($subscription->status, ['canceled', 'incomplete_expired'], true)) {
                        return $client->billingPortal->sessions->create(['customer' => $user->stripe_id, 'return_url' => route('council.checkout'), 'configuration' => config('council.portal_configuration')])->url;
                    }
                }
                if ($cached) {
                    $previous = $client->checkout->sessions->retrieve($cached['id'], []);
                    if ($previous->status === 'complete') {
                        return $client->billingPortal->sessions->create(['customer' => $user->stripe_id, 'return_url' => route('council.checkout'), 'configuration' => config('council.portal_configuration')])->url;
                    }
                    if ($previous->status === 'open') {
                        if ($cached['fingerprint'] === $fingerprint) {
                            return $previous->url;
                        }
                        $client->checkout->sessions->expire($previous->id, []);
                    }
                }
                $session = $client->checkout->sessions->create([
                    'mode' => 'subscription',
                    'customer' => $user->stripe_id,
                    'line_items' => $selection['line_items'],
                    'subscription_data' => ['metadata' => ['type' => 'council']],
                    'integration_identifier' => 'parallax_council_'.implode('', array_map(fn () => chr(random_int(97, 122)), range(1, 8))),
                    'success_url' => route('council.checkout', ['plan' => $selection['key'], 'interval' => $selection['interval'], 'executives' => $request->input('executives', []), 'checkout' => 'pending']),
                    'cancel_url' => route('council.checkout', ['plan' => $selection['key'], 'interval' => $selection['interval'], 'executives' => $request->input('executives', [])]),
                ], ['idempotency_key' => 'council-checkout-'.$user->id.'-'.Str::uuid()]);
                Cache::put($cacheKey, ['fingerprint' => $fingerprint, 'id' => $session->id], now()->addDay());

                return $session->url;
            });
        } catch (ApiErrorException $exception) {
            // Provider messages can include credential fragments; expose a fixed message.
            abort(503, 'Council checkout is temporarily unavailable. Please try again.');
        }

        return redirect()->away($url);
    }

    public function portal(Request $request, CouncilStripe $stripe)
    {
        abort_unless($request->user()->stripe_id, 409, 'This identity does not have a billing account yet.');
        try {
            $session = $stripe->client()->billingPortal->sessions->create([
                'customer' => $request->user()->stripe_id,
                'configuration' => config('council.portal_configuration'),
                'return_url' => route('council.checkout'),
            ]);
        } catch (ApiErrorException) {
            abort(503, 'Council billing is temporarily unavailable. Please try again.');
        }

        return redirect()->away($session->url);
    }
}
