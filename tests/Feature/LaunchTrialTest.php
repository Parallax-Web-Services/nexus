<?php
namespace Tests\Feature;

use App\Models\CouncilTrial;
use App\Models\LaunchRequest;
use App\Models\User;
use App\Notifications\LaunchTrialReady;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Tests\PassportTokenTestCase;

class LaunchTrialTest extends PassportTokenTestCase
{
    private function payload(): array
    {
        config(['launch.trusted_callback' => 'https://marketing.example.test/boardroom/callback']);
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Marketing launch', [config('launch.trusted_callback')]);
        return ['client_id' => (string) $client->id, 'client_secret' => $client->plainSecret,
            'reference' => (string) Str::uuid(), 'name' => 'Example Person', 'email' => 'person@example.test', 'trial_consented' => true,
            'brief' => ['path' => 'Explore more options', 'executive' => 'pulsar', 'summary' => 'How do I find the first customers?', 'desired_outcome' => 'A customer experiment', 'context' => []]];
    }
    public function test_provisioning_is_idempotent_and_preview_cannot_access_account_data(): void
    {
        Notification::fake(); $payload = $this->payload();
        $response = $this->postJson('/api/launch/trials', $payload)->assertOk()->assertJsonPath('trial.state', 'ready')->assertJsonPath('trial.email_sent', true);
        $user = User::where('email', $payload['email'])->firstOrFail();
        $this->assertNull($user->email_verified_at); $this->assertDatabaseCount('council_trials', 0); $this->assertGuest('web');
        $this->assertSame(1, $user->ownedTeams()->count());
        Notification::assertSentTo($user, VerifyEmail::class);
        Notification::assertSentTo($user, LaunchTrialReady::class, function ($notice) use ($user) {
            $mail = $notice->toMail($user);
            $this->assertNotNull($notice->setupUrl);
            $this->assertSame('Set up my account password', $mail->actionText);
            $this->assertSame($notice->setupUrl, $mail->actionUrl);
            $this->assertStringContainsString('Verify this email, then activate your 14-day trial', implode(' ', $mail->introLines));

            return true;
        });
        $this->assertDatabaseCount('password_resets', 1);
        $token = $response->json('access_token');
        $this->getWithToken($token, '/api/launch/'.$payload['reference'].'/context')->assertOk()->assertExactJson([
            'reference' => $payload['reference'], 'subject' => (string) $user->id, 'fingerprint' => LaunchRequest::first()->fingerprint, 'brief' => $payload['brief']]);
        $this->getWithToken($token, '/api/me')->assertForbidden();
        $this->getWithToken($token, '/api/v1/identity')->assertForbidden();
        $launch = LaunchRequest::first();
        $launch->update(['preview_expires_at' => now()->subMinute()]);
        $this->getWithToken($token, '/api/launch/'.$payload['reference'].'/context')->assertForbidden();
        $this->postJson('/api/launch/trials', $payload)->assertOk();
        $this->getWithToken($token, '/api/launch/'.$payload['reference'].'/context')->assertUnauthorized();
        $this->assertDatabaseCount('launch_requests', 1); $this->assertSame(1, User::where('email', $payload['email'])->count());
        $payload['brief']['summary'] = 'Different question'; $this->postJson('/api/launch/trials', $payload)->assertConflict();
    }
    public function test_verified_existing_email_gets_a_preview_without_new_account_setup(): void
    {
        Notification::fake();
        $user = User::factory()->withPersonalTeam()->create([
            'name' => 'Returning Person', 'email' => 'person@example.test', 'email_verified_at' => now(),
        ]);
        $original = $user->getRawOriginal();
        $teamIds = $user->ownedTeams()->pluck('id')->all();
        $payload = $this->payload();
        $payload['email'] = 'PERSON@EXAMPLE.TEST';

        $response = $this->postJson('/api/launch/trials', $payload)
            ->assertOk()->assertJsonPath('trial.state', 'ready')->assertJsonPath('trial.email_sent', true);

        $this->assertSame(1, User::whereRaw('LOWER(email) = ?', ['person@example.test'])->count());
        $this->assertFalse(LaunchRequest::firstOrFail()->created_user);
        $this->assertSame($original['name'], $user->fresh()->name);
        $this->assertSame($original['password'], $user->fresh()->password);
        $this->assertSame($original['email'], $user->fresh()->email);
        $this->assertSame($original['email_verified_at'], $user->fresh()->getRawOriginal('email_verified_at'));
        $this->assertSame($teamIds, $user->fresh()->ownedTeams()->pluck('id')->all());
        $this->assertDatabaseCount('password_resets', 0);
        $this->assertDatabaseCount('council_trials', 0);
        $this->assertGuest('web');

        Notification::assertNotSentTo($user, VerifyEmail::class);
        Notification::assertSentTo($user, LaunchTrialReady::class, function ($notice) use ($user) {
            $mail = $notice->toMail($user);
            $this->assertNull($notice->setupUrl);
            $this->assertSame('Sign in and open my answer', $mail->actionText);
            $this->assertSame($notice->claimUrl, $mail->actionUrl);
            $this->assertStringContainsString('Sign in, then activate your 14-day trial', implode(' ', $mail->introLines));
            $this->assertStringNotContainsString('verify', strtolower(implode(' ', $mail->introLines)));

            return true;
        });

        $token = $response->json('access_token');
        $this->getWithToken($token, '/api/launch/'.$payload['reference'].'/context')
            ->assertOk()->assertJsonPath('subject', (string) $user->id)->assertJsonPath('brief', $payload['brief']);
        $this->getWithToken($token, '/api/me')->assertForbidden();
    }

    public function test_unverified_existing_email_still_needs_standard_verification(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'person@example.test']);
        $payload = $this->payload();

        $this->postJson('/api/launch/trials', $payload)->assertOk();

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertDatabaseCount('password_resets', 0);
        $this->assertDatabaseCount('council_trials', 0);
        Notification::assertSentTo($user, LaunchTrialReady::class, function ($notice) use ($user) {
            $mail = $notice->toMail($user);
            $this->assertNull($notice->setupUrl);
            $this->assertSame($notice->claimUrl, $mail->actionUrl);
            $this->assertStringContainsString('Verify this email, then activate your 14-day trial', implode(' ', $mail->introLines));
            $this->actingAs($user, 'web')->post($notice->claimUrl)->assertRedirect(route('verification.notice'));

            return true;
        });
        $this->assertDatabaseCount('council_trials', 0);
    }

    public function test_provisioning_requires_the_trusted_confidential_client_and_consent(): void
    {
        Notification::fake(); $payload = $this->payload();
        $this->postJson('/api/launch/trials', array_replace($payload, ['client_secret' => 'wrong']))->assertUnauthorized();
        $other = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Other app', ['https://other.example.test/callback']);
        $this->postJson('/api/launch/trials', array_replace($payload, ['client_id' => (string) $other->id, 'client_secret' => $other->plainSecret]))->assertUnauthorized();
        $this->postJson('/api/launch/trials', array_replace($payload, ['trial_consented' => false]))->assertUnprocessable();
        $this->assertDatabaseCount('launch_requests', 0); Notification::assertNothingSent();
    }
    public function test_trial_activation_uses_standard_auth_verification_and_one_time_claim(): void
    {
        Notification::fake(); $payload = $this->payload(); $this->postJson('/api/launch/trials', $payload)->assertOk();
        $launch = LaunchRequest::firstOrFail(); $user = $launch->user; $url = '';
        Notification::assertSentTo($user, LaunchTrialReady::class, function ($notice) use (&$url) { $url = $notice->claimUrl; return true; });
        $this->get($url)->assertRedirect(route('login')); $this->assertDatabaseCount('council_trials', 0);
        $this->actingAs($user, 'web')->post($url)->assertRedirect(route('verification.notice')); $this->assertNull($user->fresh()->email_verified_at);
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($user, 'web')->get($url)->assertOk(); $this->assertDatabaseCount('council_trials', 0);
        $this->post($url)->assertRedirect(config('launch.continuum_url').'/launch/'.$launch->id);
        $trial = CouncilTrial::firstOrFail(); $this->assertSame($user->id, $trial->user_id);
        $this->assertEqualsWithDelta(14, now()->diffInDays($trial->expires_at), .01);
        $this->assertContains('continuum', app(\App\Support\CouncilEntitlements::class)->services($user->fresh()));
        $this->post($url)->assertForbidden();
    }
    public function test_existing_accounts_are_not_changed_and_expired_trials_do_not_restart(): void
    {
        Notification::fake(); $user = User::factory()->create(['email' => 'person@example.test', 'email_verified_at' => now()]);
        $password = $user->password;
        CouncilTrial::create(['user_id' => $user->id, 'services' => config('launch.services'), 'started_at' => now()->subDays(20), 'expires_at' => now()->subDays(6)]);
        $expiry = CouncilTrial::first()->expires_at;
        $payload = $this->payload(); $this->postJson('/api/launch/trials', $payload)->assertOk()->assertJsonPath('trial.state', 'expired');
        $launch = LaunchRequest::firstOrFail(); $url = '';
        Notification::assertSentTo($user, LaunchTrialReady::class, function ($notice) use (&$url) { $url = $notice->claimUrl; return $notice->setupUrl === null; });
        $this->assertSame($password, $user->fresh()->password); $this->assertSame($user->name, $user->fresh()->name);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($other, 'web')->post($url)->assertForbidden();
        $this->actingAs($user, 'web')->post($url)->assertRedirect();
        $this->assertEquals($expiry, CouncilTrial::first()->expires_at); $this->assertDatabaseCount('council_trials', 1);
        $this->assertNotContains('continuum', app(\App\Support\CouncilEntitlements::class)->services($user->fresh()));
    }
}
