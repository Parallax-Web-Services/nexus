<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\ClientRepository;

abstract class PassportTokenTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Generate test-only keys in memory; never use the deployment's keys.
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $privateKey);
        config([
            'passport.private_key' => $privateKey,
            'passport.public_key' => openssl_pkey_get_details($key)['key'],
        ]);

        app(ClientRepository::class)->createPersonalAccessGrantClient('Test personal tokens', 'users');

        Route::get('/_test/token-scopes', function (Request $request) {
            return [
                'read' => $request->user()->tokenCan('read'),
                'delete' => $request->user()->tokenCan('delete'),
                'full_access' => $request->user()->tokenCan('full-access'),
            ];
        })->middleware('auth:api');
    }

    protected function getWithToken(string $token, string $uri = '/api/me'): TestResponse
    {
        // A new HTTP request must resolve its own guard and bearer token.
        $this->app['auth']->forgetGuards();

        return $this->getJson($uri, ['Authorization' => 'Bearer '.$token]);
    }
}
