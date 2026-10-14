<?php

namespace Tests\Feature;

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\PassportTokenTestCase;

class CreateApiTokenTest extends PassportTokenTestCase
{
    public function test_api_tokens_can_be_created_and_authenticate_the_existing_api_guard()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user, 'web');

        $response = $this->post('/user/api-tokens', [
            'name' => 'Test Token',
            'permissions' => ['read', 'update', 'missing-permission'],
        ]);

        $response->assertRedirect()->assertSessionHas('flash.token');
        $token = $user->tokens()->sole();
        $this->assertSame('Test Token', $token->name);
        $this->assertSame(['read', 'update'], $token->scopes);
        $this->assertFalse($token->can('delete'));

        $bearer = session('flash.token');
        $this->getWithToken($bearer)->assertOk()->assertJsonPath('id', $user->id);
        $this->getWithToken($bearer, '/_test/token-scopes')
            ->assertOk()->assertExactJson(['read' => true, 'delete' => false, 'full_access' => false]);
    }

    public function test_token_manager_lists_only_own_active_personal_tokens()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $personal = $user->createToken('Personal Token', ['read'])->token;
        $revoked = $user->createToken('Revoked Token', ['read'])->token;
        $revoked->revoke();
        User::factory()->create()->createToken('Another user token', ['read']);
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'Product app', ['https://example.test/callback']
        );
        Passport::token()->forceFill([
            'id' => 'delegated-token',
            'user_id' => $user->id,
            'client_id' => $client->id,
            'name' => 'OAuth authorization',
            'scopes' => ['full-access'],
            'revoked' => false,
            'expires_at' => now()->addHour(),
        ])->save();

        $this->actingAs($user, 'web')->get('/user/api-tokens')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('API/Index')
                ->has('tokens', 1)
                ->where('tokens.0.id', $personal->id)
                ->where('tokens.0.abilities', ['read'])
                ->missing('tokens.0.client')
                ->missing('tokens.0.accessToken'));
    }

    public function test_existing_full_access_tokens_keep_their_scope()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $existing = $user->createToken('Existing integration', ['full-access']);

        $this->getWithToken($existing->accessToken)->assertOk()->assertJsonPath('id', $user->id);
        $this->getWithToken($existing->accessToken, '/_test/token-scopes')
            ->assertOk()->assertJsonPath('full_access', true);
    }

    public function test_product_oauth_authorization_code_flow_still_issues_working_tokens()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'Existing product', ['https://product.example.test/oauth/callback']
        );

        $authorization = $this->actingAs($user, 'web')->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => 'https://product.example.test/oauth/callback',
            'response_type' => 'code',
            'scope' => 'full-access',
            'state' => 'product-session-state',
        ]));
        $authorization->assertRedirect();
        parse_str(parse_url($authorization->headers->get('Location'), PHP_URL_QUERY), $callback);
        $this->assertSame('product-session-state', $callback['state']);
        $this->assertArrayHasKey('code', $callback);

        $exchange = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'redirect_uri' => 'https://product.example.test/oauth/callback',
            'code' => $callback['code'],
        ]);
        $exchange->assertOk()->assertJsonStructure(['access_token', 'refresh_token']);

        $this->getWithToken($exchange->json('access_token'))
            ->assertOk()->assertJsonPath('id', $user->id);
        $this->getWithToken($exchange->json('access_token'), '/_test/token-scopes')
            ->assertOk()->assertJsonPath('full_access', true);
    }
}
