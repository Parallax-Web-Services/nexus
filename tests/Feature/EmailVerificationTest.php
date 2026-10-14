<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered()
    {
        Notification::fake();
        $this->assertTrue(Features::enabled(Features::emailVerification()));
        $this->assertTrue(Route::has('verification.notice'));
        $user = User::factory()->withPersonalTeam()->unverified()->create();

        $this->actingAs($user)->withSession(['url.intended' => '/oauth/authorize?client_id=fixture-client'])
            ->get('/email/verify')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/VerifyEmail')->where('email', $user->email))
            ->assertSessionHas('url.intended', '/oauth/authorize?client_id=fixture-client');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        Notification::assertNothingSent();
    }

    public function test_email_can_be_verified()
    {
        Event::fake();

        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(RouteServiceProvider::HOME.'?verified=1');
    }

    public function test_email_can_not_verified_with_invalid_hash()
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl)->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_expired_signed_link_does_not_verify_the_account(): void
    {
        $user = User::factory()->unverified()->create();
        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($verificationUrl)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_another_signed_in_account_cannot_use_the_verification_link(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);

        $this->actingAs($other)->get($verificationUrl)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_unverified_dashboard_access_redirects_to_notice(): void
    {
        $user = User::factory()->withPersonalTeam()->unverified()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));
    }
}
