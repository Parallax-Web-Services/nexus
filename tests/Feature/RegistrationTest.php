<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        if (! Features::enabled(Features::registration())) {
            return $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registration_screen_cannot_be_rendered_if_support_is_disabled()
    {
        if (Features::enabled(Features::registration())) {
            return $this->markTestSkipped('Registration support is enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_new_users_can_register()
    {
        if (! Features::enabled(Features::registration())) {
            return $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_service_registration_resumes_oauth_and_creates_a_personal_team()
    {
        if (! Features::enabled(Features::registration())) {
            return $this->markTestSkipped('Registration support is not enabled.');
        }

        config([
            'nexus.services.slipstream.domain' => 'my.slipstream.test',
            'nexus.services.slipstream.url' => 'https://slipstream.test',
        ]);

        $email = 'alpha-pilot@example.test';
        $returnTo = 'https://my.slipstream.test/oauth/authorize?'.http_build_query([
            'client_id' => 'slipstream-client',
            'redirect_uri' => 'https://slipstream.test/oauth/callback',
            'response_type' => 'code',
            'state' => 'slipstream-session-state',
            'scope' => '*',
        ]);

        $this->get('https://my.slipstream.test/register?'.http_build_query([
            'return_to' => $returnTo,
            'email' => $email,
        ]))
            ->assertOk()
            ->assertSessionHas('url.intended', $returnTo)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Register')
                ->where('registration.email', $email)
                ->where('registration.service', 'slipstream'));

        $response = $this->post('https://my.slipstream.test/register', [
            'name' => 'Alpha Pilot',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $user = User::query()->where('email', $email)->firstOrFail();
        $personalTeam = $user->ownedTeams()
            ->where('personal_team', true)
            ->sole();

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect($returnTo);
        $this->assertSame($personalTeam->id, $user->fresh()->currentTeam->id);
        $this->assertSame($personalTeam->id, $user->fresh()->current_team_id);
    }

    public function test_service_registration_rejects_an_external_return_url()
    {
        if (! Features::enabled(Features::registration())) {
            return $this->markTestSkipped('Registration support is not enabled.');
        }

        config([
            'nexus.services.slipstream.domain' => 'my.slipstream.test',
            'nexus.services.slipstream.url' => 'https://slipstream.test',
        ]);

        $this->get('https://my.slipstream.test/register?'.http_build_query([
            'return_to' => 'https://attacker.example/collect',
            'email' => 'victim@example.test',
        ]))
            ->assertOk()
            ->assertSessionMissing('url.intended')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Register')
                ->where('registration', null));
    }

    public function test_service_registration_rejects_internal_oauth_for_an_external_callback()
    {
        if (! Features::enabled(Features::registration())) {
            return $this->markTestSkipped('Registration support is not enabled.');
        }

        config([
            'nexus.services.slipstream.domain' => 'my.slipstream.test',
            'nexus.services.slipstream.url' => 'https://slipstream.test',
        ]);

        $returnTo = 'https://my.slipstream.test/oauth/authorize?'.http_build_query([
            'client_id' => 'slipstream-client',
            'redirect_uri' => 'https://attacker.example/collect',
            'response_type' => 'code',
        ]);

        $this->get('https://my.slipstream.test/register?'.http_build_query([
            'return_to' => $returnTo,
            'email' => 'victim@example.test',
        ]))
            ->assertOk()
            ->assertSessionMissing('url.intended')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Register')
                ->where('registration', null));
    }
}
