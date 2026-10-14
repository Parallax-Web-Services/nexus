<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\PassportTokenTestCase;

class ApiTokenPermissionsTest extends PassportTokenTestCase
{
    public function test_api_token_permission_changes_rotate_the_token_and_revoke_the_old_bearer()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $original = $user->createToken('Test Token', ['create', 'read']);

        $this->getWithToken($original->accessToken, '/_test/token-scopes')
            ->assertOk()->assertJsonPath('read', true);

        $response = $this->actingAs($user, 'web')->put('/user/api-tokens/'.$original->token->id, [
            'permissions' => ['delete', 'missing-permission'],
        ]);

        $response->assertRedirect()->assertSessionHas('flash.token');
        $replacementBearer = session('flash.token');
        $this->assertTrue($original->token->fresh()->revoked);
        $replacement = $user->tokens()->where('revoked', false)->sole();
        $this->assertSame('Test Token', $replacement->name);
        $this->assertSame(['delete'], $replacement->scopes);
        $this->assertNotSame($original->token->id, $replacement->id);

        $this->getWithToken($original->accessToken)->assertUnauthorized();
        $this->getWithToken($replacementBearer, '/_test/token-scopes')
            ->assertOk()->assertExactJson(['read' => false, 'delete' => true, 'full_access' => false]);
    }

    public function test_users_cannot_rotate_another_users_token()
    {
        $owner = User::factory()->create();
        $original = $owner->createToken('Private token', ['read']);

        $this->actingAs(User::factory()->withPersonalTeam()->create(), 'web')
            ->put('/user/api-tokens/'.$original->token->id, ['permissions' => ['delete']])
            ->assertNotFound();

        $this->assertFalse($original->token->fresh()->revoked);
        $this->assertSame(['read'], $original->token->fresh()->scopes);
    }

    public function test_product_oauth_tokens_cannot_be_changed_through_the_personal_token_manager()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'Product app', ['https://example.test/callback']
        );
        $token = Passport::token()->forceFill([
            'id' => 'product-token',
            'user_id' => $user->id,
            'client_id' => $client->id,
            'name' => 'OAuth authorization',
            'scopes' => ['full-access'],
            'revoked' => false,
            'expires_at' => now()->addHour(),
        ]);
        $token->save();

        $this->actingAs($user, 'web')
            ->put('/user/api-tokens/'.$token->id, ['permissions' => ['delete']])
            ->assertNotFound();
        $this->delete('/user/api-tokens/'.$token->id)->assertNotFound();

        $this->assertFalse($token->fresh()->revoked);
        $this->assertSame(['full-access'], $token->fresh()->scopes);
    }

    public function test_all_token_permissions_can_be_cleared()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $original = $user->createToken('Test Token', ['read']);

        $this->actingAs($user, 'web')
            ->put('/user/api-tokens/'.$original->token->id, ['permissions' => []])
            ->assertRedirect()->assertSessionHas('flash.token');

        $this->assertTrue($original->token->fresh()->revoked);
        $this->getWithToken(session('flash.token'), '/_test/token-scopes')
            ->assertOk()->assertExactJson(['read' => false, 'delete' => false, 'full_access' => false]);
    }
}
