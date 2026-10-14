<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\PassportTokenTestCase;

class DeleteApiTokenTest extends PassportTokenTestCase
{
    public function test_api_tokens_can_be_revoked_and_no_longer_authenticate()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('Test Token', ['create', 'read']);
        $this->getWithToken($token->accessToken)->assertOk();

        $this->actingAs($user, 'web')->delete('/user/api-tokens/'.$token->token->id)
            ->assertRedirect();

        $this->assertTrue($token->token->fresh()->revoked);
        $this->getWithToken($token->accessToken)->assertUnauthorized();
    }

    public function test_users_cannot_revoke_another_users_token()
    {
        $token = User::factory()->create()->createToken('Private token', ['read']);

        $this->actingAs(User::factory()->withPersonalTeam()->create(), 'web')
            ->delete('/user/api-tokens/'.$token->token->id)->assertNotFound();

        $this->assertFalse($token->token->fresh()->revoked);
    }
}
