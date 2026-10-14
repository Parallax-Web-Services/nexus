<?php

namespace Tests\Feature;

use Laravel\Passport\ClientRepository;
use Tests\PassportTokenTestCase;

class OAuthBrowserLoginTest extends PassportTokenTestCase
{
    public function test_an_oauth_browser_guest_is_sent_to_login_with_the_authorization_request_preserved(): void
    {
        $url = $this->authorizationUrl();
        $this->get($url)->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', function (string $intended) use ($url): bool {
                parse_str(parse_url($url, PHP_URL_QUERY), $expected);
                parse_str(parse_url($intended, PHP_URL_QUERY), $actual);

                return parse_url($intended, PHP_URL_PATH) === '/oauth/authorize' && $expected == $actual;
            });
    }

    public function test_a_json_oauth_guest_still_receives_an_unauthorized_response(): void
    {
        $this->getJson($this->authorizationUrl())->assertUnauthorized();
    }

    private function authorizationUrl(): string
    {
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'Example browser test', ['http://consumer.test/auth/callback'], false
        );

        return '/oauth/authorize?'.http_build_query([
            'client_id' => $client->id, 'redirect_uri' => 'http://consumer.test/auth/callback',
            'response_type' => 'code', 'scope' => 'identity:read', 'state' => 'random-test-state',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', str_repeat('v', 64), true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);
    }
}
