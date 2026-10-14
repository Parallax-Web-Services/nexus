<?php

namespace Tests\Feature;

use App\Models\CouncilSubscription;
use App\Models\Plan;
use App\Models\User;
use App\Support\CouncilEntitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Laravel\Passport\Passport;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;
use Tests\TestCase;

class CouncilBillingTest extends TestCase
{
    use RefreshDatabase;

    private CouncilStripeFake $http;

    protected function setUp(): void
    {
        parent::setUp();
        $catalog = config('council.catalog');
        $catalog['livemode'] = false;
        foreach ($catalog['plans'] as $key => &$plan) {
            foreach ($plan['prices'] as $interval => &$price) {
                $price['id'] = 'price_'.$key.'_'.$interval;
            }
            unset($price);
        }
        config(['council.catalog' => $catalog, 'council.livemode' => false, 'billing.accounts.parallax.secret' => 'rk_'.'test_fixture', 'council.webhook_secret' => 'fixture-signing-secret', 'council.portal_configuration' => 'bpc_fixture']);
        $this->http = new CouncilStripeFake;
        ApiRequestor::setHttpClient($this->http);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(new CurlClient);
        parent::tearDown();
    }

    private function user(): User
    {
        return User::factory()->create(['stripe_id' => 'cus_council']);
    }

    private function subscription(string $status = 'active', array $prices = ['price_full_monthly']): array
    {
        return ['id' => 'sub_council', 'object' => 'subscription', 'customer' => 'cus_council', 'status' => $status, 'metadata' => ['type' => 'council'], 'items' => ['data' => array_map(fn ($id) => ['price' => ['id' => $id]], $prices)]];
    }

    private function webhook(string $type, array $object, bool $valid = true, bool $live = false)
    {
        $body = json_encode(['id' => 'evt_fixture', 'object' => 'event', 'livemode' => $live, 'type' => $type, 'created' => time(), 'data' => ['object' => $object]]);
        $time = time();
        $signature = $valid ? hash_hmac('sha256', $time.'.'.$body, 'fixture-signing-secret') : 'invalid';

        return $this->call('POST', '/stripe/council/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$time.',v1='.$signature], $body);
    }

    public function test_review_uses_approved_annual_terms_and_preserves_selection_through_signin(): void
    {
        $this->get('/council/checkout?plan=founder&interval=annual')->assertOk()->assertSee('$690')->assertSee('Billed annually')->assertSee('Plexus');
        $this->assertStringContainsString('plan=founder', session('url.intended'));
        $this->assertSame([], $this->http->calls);
    }

    public function test_custom_selection_is_monthly_and_cannot_include_a_bundle(): void
    {
        $this->get('/council/checkout?plan=custom&interval=monthly&executives[]=continuum&executives[]=parity')->assertOk()->assertSee('$58');
        $this->getJson('/council/checkout?plan=custom&interval=annual&executives[]=continuum')->assertUnprocessable();
        $this->getJson('/council/checkout?plan=custom&executives[]=founder')->assertUnprocessable();
        $this->getJson('/council/checkout?plan=unknown')->assertUnprocessable();
    }

    public function test_price_tampering_and_anonymous_purchase_are_rejected(): void
    {
        $this->getJson('/council/checkout?plan=full&price_id=price_cheaper')->assertUnprocessable();
        $this->post('/council/checkout', ['plan' => 'full'])->assertRedirect(route('login'));
        $this->actingAs($this->user())->postJson('/council/checkout', ['plan' => 'full', 'amount' => 1])->assertUnprocessable();
        $this->assertSame([], $this->http->calls);
    }

    public function test_checkout_uses_server_prices_and_reuses_an_open_session_across_browser_sessions(): void
    {
        $user = $this->user();
        $this->http->responses = [
            ['object' => 'list', 'data' => [], 'has_more' => false],
            ['id' => 'cs_council', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/fixture', 'expires_at' => time() + 3600],
            ['object' => 'list', 'data' => [], 'has_more' => false],
            ['id' => 'cs_council', 'object' => 'checkout.session', 'status' => 'open', 'url' => 'https://checkout.stripe.com/c/pay/fixture'],
        ];
        $data = ['plan' => 'growth', 'interval' => 'annual'];
        $this->actingAs($user)->post('/council/checkout', $data)->assertRedirect('https://checkout.stripe.com/c/pay/fixture');
        session()->flush();
        $this->actingAs($user)->post('/council/checkout', $data)->assertRedirect('https://checkout.stripe.com/c/pay/fixture');
        $params = $this->http->calls[1]['params'];
        $this->assertSame([['price' => 'price_growth_annual', 'quantity' => 1]], $params['line_items']);
        $this->assertSame('cus_council', $params['customer']);
        $this->assertArrayNotHasKey('payment_method_types', $params);
        $this->assertArrayNotHasKey('automatic_tax', $params);
        $this->assertSame('council', $params['subscription_data']['metadata']['type']);
        $this->assertMatchesRegularExpression('/^parallax_council_[a-z]{8}$/', $params['integration_identifier']);
        $this->assertStringContainsString('plan=growth', $params['cancel_url']);
        $this->assertSame(4, count($this->http->calls));
        $this->assertDatabaseCount('council_subscriptions', 0);
    }

    public function test_an_existing_council_opens_portal_instead_of_creating_another_subscription(): void
    {
        $user = $this->user();
        app(CouncilEntitlements::class)->sync($this->subscription(), 100);
        $this->http->responses = [['id' => 'bps_fixture', 'object' => 'billing_portal.session', 'url' => 'https://billing.stripe.com/p/session/fixture']];
        $this->actingAs($user)->post('/council/checkout', ['plan' => 'founder'])->assertRedirect('https://billing.stripe.com/p/session/fixture');
        $this->assertStringContainsString('/billing_portal/sessions', $this->http->calls[0]['url']);
    }

    public function test_invalid_signatures_missing_configuration_and_wrong_environment_fail_closed(): void
    {
        $this->webhook('customer.subscription.created', $this->subscription(), valid: false)->assertStatus(400);
        $this->webhook('customer.subscription.created', $this->subscription(), live: true)->assertStatus(400);
        config(['council.webhook_secret' => null]);
        $this->webhook('customer.subscription.created', $this->subscription())->assertStatus(503);
        $this->assertSame([], $this->http->calls);
        $this->assertDatabaseCount('council_subscriptions', 0);
    }

    public function test_duplicate_webhooks_and_plan_changes_do_not_leave_old_executive_access(): void
    {
        $user = $this->user();
        $this->http->responses = [$this->subscription(), $this->subscription(), $this->subscription('active', ['price_parity_monthly'])];
        $this->webhook('customer.subscription.created', $this->subscription())->assertOk();
        $this->webhook('customer.subscription.created', $this->subscription())->assertOk();
        $this->assertDatabaseCount('council_subscriptions', 1);
        $this->assertCount(6, app(CouncilEntitlements::class)->services($user));
        $this->webhook('customer.subscription.updated', $this->subscription())->assertOk();
        $this->assertSame(['parity'], app(CouncilEntitlements::class)->services($user));
    }

    public function test_invoice_failure_and_recovery_refresh_authoritative_subscription_state(): void
    {
        $user = $this->user();
        app(CouncilEntitlements::class)->sync($this->subscription(), 1);
        $invoice = ['id' => 'in_fixture', 'object' => 'invoice', 'parent' => ['subscription_details' => ['subscription' => 'sub_council']]];
        $this->http->responses = [$this->subscription('past_due'), $this->subscription('active')];
        $this->webhook('invoice.payment_failed', $invoice)->assertOk();
        $this->assertSame([], app(CouncilEntitlements::class)->services($user));
        $this->webhook('invoice.paid', $invoice)->assertOk();
        $this->assertCount(6, app(CouncilEntitlements::class)->services($user));
    }

    public function test_checkout_does_not_grant_access_before_payment_and_subscription_confirmation(): void
    {
        $user = $this->user();
        $session = ['id' => 'cs_council', 'object' => 'checkout.session', 'mode' => 'subscription', 'payment_status' => 'unpaid', 'subscription' => 'sub_council'];
        $this->webhook('checkout.session.completed', $session)->assertOk();
        $this->assertSame([], $this->http->calls);
        $this->assertSame([], app(CouncilEntitlements::class)->services($user));
        $this->http->responses = [$this->subscription()];
        $session['payment_status'] = 'paid';
        $this->webhook('checkout.session.async_payment_succeeded', $session)->assertOk();
        $this->assertCount(6, app(CouncilEntitlements::class)->services($user));
    }

    public function test_cancel_at_period_end_and_stale_events_cannot_extend_access(): void
    {
        $user = $this->user();
        $sync = app(CouncilEntitlements::class);
        $subscription = $this->subscription();
        $subscription['cancel_at'] = now()->addHour()->timestamp;
        $sync->sync($subscription, 100);
        $this->assertCount(6, $sync->services($user));
        $this->travel(2)->hours();
        $this->assertSame([], $sync->services($user));
        $sync->sync($this->subscription('canceled'), 200);
        $sync->sync($this->subscription(), 100);
        $this->assertSame([], $sync->services($user));
        $sync->sync($this->subscription(), 200);
        $this->assertSame([], $sync->services($user));
    }

    public function test_customer_graph_controls_ownership_and_nexus_identity_exposes_only_owned_roles(): void
    {
        $user = $this->user();
        $other = User::factory()->create(['stripe_id' => 'cus_other']);
        $subscription = $this->subscription('active', ['price_founder_monthly']);
        $subscription['metadata']['user_id'] = $other->id;
        app(CouncilEntitlements::class)->sync($subscription, 100);
        $this->assertSame([], app(CouncilEntitlements::class)->services($other));
        Passport::actingAs($user, ['*']);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('service_entitlements', ['continuum', 'slipstream', 'plexus']);
        $this->assertSame($user->id, CouncilSubscription::first()->user_id);
    }

    public function test_live_catalog_cannot_use_test_credentials(): void
    {
        config(['council.livemode' => true, 'council.catalog.livemode' => true]);
        $this->actingAs($this->user())->post('/council/checkout', ['plan' => 'full'])->assertStatus(503);
        $this->assertSame([], $this->http->calls);
    }

    public function test_missing_webhook_configuration_disables_checkout_before_any_stripe_request(): void
    {
        config(['council.webhook_secret' => null]);
        $this->actingAs($this->user())->post('/council/checkout', ['plan' => 'full'])->assertStatus(503);
        $this->get('/council/checkout')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('CouncilCheckout')->where('ready', false));
        $this->assertSame([], $this->http->calls);
    }

    public function test_portal_uses_the_signed_in_customer_and_period_end_cancellation_configuration(): void
    {
        $this->http->responses = [['id' => 'bps_fixture', 'object' => 'billing_portal.session', 'url' => 'https://billing.stripe.com/p/session/fixture']];
        $this->actingAs($this->user())->get('/council/portal?customer=cus_other')->assertRedirect('https://billing.stripe.com/p/session/fixture');
        $this->assertSame('cus_council', $this->http->calls[0]['params']['customer']);
        $this->assertSame(config('council.portal_configuration'), $this->http->calls[0]['params']['configuration']);
    }

    public function test_catalog_sync_is_idempotent_and_maps_bundle_roles(): void
    {
        $this->artisan('council:sync-catalog')->assertSuccessful();
        $this->artisan('council:sync-catalog')->assertSuccessful();
        $this->assertDatabaseCount('plans', 12);
        $plan = Plan::where('stripe_price_id', 'price_growth_annual')->first();
        $this->assertEqualsCanonicalizing(['continuum', 'pulsar', 'parity'], $plan->services->pluck('key')->all());
    }

    public function test_changing_a_selection_expires_the_previous_open_checkout(): void
    {
        $user = $this->user();
        $this->http->responses = [
            ['object' => 'list', 'data' => [], 'has_more' => false],
            ['id' => 'cs_old', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/old'],
            ['object' => 'list', 'data' => [], 'has_more' => false],
            ['id' => 'cs_old', 'object' => 'checkout.session', 'status' => 'open', 'url' => 'https://checkout.stripe.com/old'],
            ['id' => 'cs_old', 'object' => 'checkout.session', 'status' => 'expired'],
            ['id' => 'cs_new', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/new'],
        ];
        $this->actingAs($user)->post('/council/checkout', ['plan' => 'founder'])->assertRedirect('https://checkout.stripe.com/old');
        $this->actingAs($user)->post('/council/checkout', ['plan' => 'full'])->assertRedirect('https://checkout.stripe.com/new');
        $this->assertStringContainsString('/cs_old/expire', $this->http->calls[4]['url']);
        $this->assertSame('price_full_monthly', $this->http->calls[5]['params']['line_items'][0]['price']);
    }
}

class CouncilStripeFake implements ClientInterface
{
    public array $calls = [];

    public array $responses = [];

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->calls[] = ['method' => $method, 'url' => $absUrl, 'params' => $params];
        if ($this->responses === []) {
            throw new \RuntimeException('Unexpected Stripe request: '.$absUrl);
        }

        return [json_encode(array_shift($this->responses)), 200, []];
    }
}
