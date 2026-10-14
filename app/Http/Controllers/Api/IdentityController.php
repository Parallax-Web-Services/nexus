<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Bridge\AccessTokenRepository;
use Laravel\Passport\Bridge\RefreshTokenRepository;
use Laravel\Passport\Passport;

class IdentityController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->tokenCan('identity:read'), 403);

        return response()->json([
            'issuer' => rtrim(config('app.url'), '/'),
            'subject' => (string) $user->getAuthIdentifier(),
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'email_verified' => $user->email_verified_at !== null,
        ]);
    }

    public function destroy(
        Request $request,
        AccessTokenRepository $accessTokens,
        RefreshTokenRepository $refreshTokens,
    ): Response {
        $user = $request->user();
        abort_unless($user->tokenCan('identity:read'), 403);

        $token = $user->currentAccessToken();

        // Cookie-backed transient tokens are not a revocable OAuth session.
        abort_unless($request->bearerToken() && $token instanceof AccessToken, 403);

        $tokenId = $token->oauth_access_token_id;

        Passport::token()->getConnection()->transaction(function () use ($tokenId, $accessTokens, $refreshTokens) {
            $accessTokens->revokeAccessToken($tokenId);

            Passport::refreshToken()->newQuery()
                ->where('access_token_id', $tokenId)
                ->pluck('id')
                ->each(fn (string $id) => $refreshTokens->revokeRefreshToken($id));
        });

        return response()->noContent();
    }
}
