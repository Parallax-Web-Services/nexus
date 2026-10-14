<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountShellPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_navigation_retains_handoff_theme_and_current_team(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user)->get('/dashboard?workspace=slipstream')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard')->where('shell.workspace', 'slipstream'));
        $this->get('/user/profile')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Show')->where('shell.workspace', 'slipstream')
            ->where('user.current_team.id', $user->fresh()->current_team_id));
        $this->get('/billing')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Billing')->where('shell.workspace', 'slipstream'));
    }

    public function test_service_host_dashboard_keeps_the_account_shell_available(): void
    {
        config(['nexus.services.slipstream.domain' => 'my.slipstream.test', 'nexus.services.slipstream.url' => 'https://slipstream.test']);
        $this->actingAs(User::factory()->withPersonalTeam()->create())->get('https://my.slipstream.test/dashboard')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Dashboard')->where('shell.workspace', 'slipstream'));
    }

    public function test_signed_in_council_review_uses_the_account_shell_and_keeps_checkout_disabled_without_configuration(): void
    {
        config(['council.webhook_secret' => null]);
        $this->actingAs(User::factory()->withPersonalTeam()->create())->get('/council/checkout?plan=continuum&workspace=continuum')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('CouncilCheckout')
            ->where('shell.workspace', 'continuum')->where('selection.key', 'continuum')
            ->where('ready', false)->has('csrfToken'));
    }
}
