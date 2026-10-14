<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\TransientToken;
use Tests\PassportTokenTestCase;

class IdentityApiTest extends PassportTokenTestCase
{
    public function test_identity_returns_only_the_versioned_identity_fields()
    {
        config(['app.url' => 'https://nexus.example.test/']);
        $user = User::factory()->withPersonalTeam()->create([
            'name' => 'Example Person',
            'email' => 'person@example.test',
            'email_verified_at' => null,
        ]);
        $token = $user->createToken('Identity client', ['identity:read']);

        $this->getWithToken($token->accessToken, '/api/v1/identity')
            ->assertOk()
            ->assertExactJson([
                'issuer' => 'https://nexus.example.test',
                'subject' => (string) $user->id,
                'name' => 'Example Person',
                'email' => 'person@example.test',
                'email_verified' => false,
            ]);
    }

    public function test_identity_reports_verified_email_only_when_recorded()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('Identity client', ['identity:read']);

        $this->getWithToken($token->accessToken, '/api/v1/identity')
            ->assertOk()->assertJsonPath('email_verified', true);
    }

    public function test_identity_and_revocation_require_authentication()
    {
        $this->getJson('/api/v1/identity')->assertUnauthorized();
        $this->deleteJson('/api/v1/session')->assertUnauthorized();
    }

    public function test_general_read_and_full_access_scopes_do_not_grant_identity_access()
    {
        $user = User::factory()->create();

        foreach (['read', 'full-access'] as $scope) {
            $token = $user->createToken('Wrong scope', [$scope]);

            $this->getWithToken($token->accessToken, '/api/v1/identity')->assertForbidden();
            $this->app['auth']->forgetGuards();
            $this->deleteJson('/api/v1/session', [], [
                'Authorization' => 'Bearer '.$token->accessToken,
            ])->assertForbidden();

            $this->assertFalse($token->token->fresh()->revoked);
        }
    }

    public function test_revocation_invalidates_only_the_current_bearer_and_its_refresh_tokens()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'Identity demonstration', ['https://demo.example.test/oauth/callback']
        );
        $authorization = $this->actingAs($user, 'web')->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => 'https://demo.example.test/oauth/callback',
            'response_type' => 'code',
            'scope' => 'identity:read',
            'state' => 'test-identity-session',
        ]));
        $authorization->assertRedirect();
        parse_str(parse_url($authorization->headers->get('Location'), PHP_URL_QUERY), $callback);

        $exchange = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'redirect_uri' => 'https://demo.example.test/oauth/callback',
            'code' => $callback['code'],
        ])->assertOk()->assertJsonStructure(['access_token', 'refresh_token']);

        $current = $user->tokens()->where('client_id', $client->id)->sole();
        $unrelated = $user->createToken('Another session', ['identity:read']);
        Passport::refreshToken()->forceFill([
            'id' => 'additional-associated-refresh',
            'access_token_id' => $current->id,
            'revoked' => false,
            'expires_at' => now()->addDay(),
        ])->save();
        Passport::refreshToken()->forceFill([
            'id' => 'unrelated-refresh',
            'access_token_id' => $unrelated->token->id,
            'revoked' => false,
            'expires_at' => now()->addDay(),
        ])->save();

        $this->getWithToken($exchange->json('access_token'), '/api/v1/identity')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->deleteJson('/api/v1/session', ['token_id' => $unrelated->token->id], [
            'Authorization' => 'Bearer '.$exchange->json('access_token'),
        ])->assertNoContent();

        $this->assertTrue($current->fresh()->revoked);
        $this->assertSame(2, Passport::refreshToken()->newQuery()
            ->where('access_token_id', $current->id)->where('revoked', true)->count());
        $this->assertFalse($unrelated->token->fresh()->revoked);
        $this->assertFalse(Passport::refreshToken()->newQuery()->findOrFail('unrelated-refresh')->revoked);
        $this->getWithToken($exchange->json('access_token'), '/api/v1/identity')->assertUnauthorized();
        $this->getWithToken($unrelated->accessToken, '/api/v1/identity')->assertOk();

        $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'refresh_token' => $exchange->json('refresh_token'),
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_web_session_cannot_revoke_an_oauth_token()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('Identity client', ['identity:read']);

        $this->actingAs($user, 'web')
            ->deleteJson('/api/v1/session', ['token_id' => $token->token->id])
            ->assertUnauthorized();

        $this->assertFalse($token->token->fresh()->revoked);
    }

    public function test_transient_authentication_cannot_revoke_an_oauth_token()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('Identity client', ['identity:read']);

        $this->actingAs($user->withAccessToken(new TransientToken), 'api')
            ->deleteJson('/api/v1/session', ['token_id' => $token->token->id], [
                'Authorization' => 'Bearer not-an-oauth-session',
            ])->assertForbidden();

        $this->assertFalse($token->token->fresh()->revoked);
    }
}
