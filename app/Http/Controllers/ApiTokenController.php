<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Jetstream\Http\Controllers\Inertia\ApiTokenController as JetstreamApiTokenController;
use Laravel\Jetstream\Jetstream;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

/**
 * Keep Jetstream's token manager on the same Passport guard as the product APIs.
 */
class ApiTokenController extends JetstreamApiTokenController
{
    public function index(Request $request)
    {
        $tokens = $request->user()->tokens()
            ->with('client')
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->get()
            ->filter(fn (Token $token) => $token->client?->hasGrantType('personal_access'))
            ->map(fn (Token $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->scopes,
                'last_used_ago' => null,
            ])
            ->values();

        return Jetstream::inertia()->render($request, 'API/Index', [
            'tokens' => $tokens,
            'availablePermissions' => Jetstream::$permissions,
            'defaultPermissions' => Jetstream::$defaultPermissions,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string'],
        ]);

        $token = $request->user()->createToken(
            $request->name,
            Jetstream::validPermissions($request->input('permissions', [])),
        );

        return back()->with('flash', ['token' => $token->accessToken]);
    }

    public function update(Request $request, $tokenId)
    {
        $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string'],
        ]);

        $replacement = Passport::token()->getConnection()->transaction(function () use ($request, $tokenId) {
            $token = $this->personalToken($request, $tokenId, true);

            // Scopes are signed into the JWT. Rotate it so removed permissions
            // stop working immediately instead of only changing the database.
            $replacement = $request->user()->createToken(
                $token->name,
                Jetstream::validPermissions($request->input('permissions')),
            );

            $token->refreshToken?->revoke();
            $token->revoke();

            return $replacement;
        });

        return back(303)->with('flash', ['token' => $replacement->accessToken]);
    }

    public function destroy(Request $request, $tokenId)
    {
        Passport::token()->getConnection()->transaction(function () use ($request, $tokenId) {
            $token = $this->personalToken($request, $tokenId, true);
            $token->refreshToken?->revoke();
            $token->revoke();
        });

        return back(303);
    }

    protected function personalToken(Request $request, string $tokenId, bool $lock = false): Token
    {
        $query = $request->user()->tokens()->with('client')->where('revoked', false);

        if ($lock) {
            $query->lockForUpdate();
        }

        $token = $query->findOrFail($tokenId);

        // Product OAuth authorizations have their own lifecycle.
        abort_unless($token->client?->hasGrantType('personal_access'), 404);

        return $token;
    }
}
