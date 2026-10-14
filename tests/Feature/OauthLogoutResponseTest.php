<?php

namespace Tests\Feature;

use App\Http\Responses\OauthLogoutResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Fortify;
use Tests\TestCase;

class OauthLogoutResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['oauth_logout.return_urls' => ['https://app.example.test/logged-out', 'https://other.example.test/logged-out']]);
    }

    public function test_deployed_apps_return_to_their_exact_logout_pages(): void
    {
        $response = app(LogoutResponse::class);
        $this->assertInstanceOf(OauthLogoutResponse::class, $response);
        foreach (config('oauth_logout.return_urls') as $target) {
            $request = Request::create('/logout', 'GET', ['redirect' => $target]);
            $result = $response->toResponse($request);
            $this->assertSame(302, $result->getStatusCode());
            $this->assertSame($target, $result->getTargetUrl());
        }
    }

    public function test_unknown_origins_paths_and_malformed_targets_keep_fortify_default(): void
    {
        foreach ([null, ['https://app.example.test/logged-out'], 'https://untrusted.example/logged-out',
            'https://app.example.test.evil.example/logged-out', 'http://app.example.test/logged-out',
            '//app.example.test/logged-out', 'https://app.example.test/dashboard',
            'https://app.example.test/logged-out?redirect=https://untrusted.example',
            'https://app.example.test/logged-out#fragment', 'https://user@app.example.test/logged-out',
            'https://app.example.test:443/logged-out'] as $target) {
            $request = Request::create('/logout', 'GET', $target === null ? [] : ['redirect' => $target]);
            $result = app(LogoutResponse::class)->toResponse($request);
            $this->assertSame(url(Fortify::redirects('logout', '/')), $result->getTargetUrl());
        }
    }

    public function test_json_keeps_204_and_inertia_receives_a_full_browser_handoff(): void
    {
        $target = 'https://app.example.test/logged-out';
        $json = Request::create('/logout', 'GET', ['redirect' => $target]);
        $json->headers->set('Accept', 'application/json');
        $this->assertSame(204, app(LogoutResponse::class)->toResponse($json)->getStatusCode());
        $request = Request::create('/logout', 'GET', ['redirect' => $target]);
        $request->headers->set('X-Inertia', 'true');
        // Inertia resolves the current request from the container.
        $this->app->instance('request', $request);
        $result = app(LogoutResponse::class)->toResponse($request);
        $this->assertSame(409, $result->getStatusCode());
        $this->assertSame($target, $result->headers->get('X-Inertia-Location'));
    }
}
