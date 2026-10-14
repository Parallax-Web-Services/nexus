<?php

namespace App\Http\Responses;

use Inertia\Inertia;
use Laravel\Fortify\Http\Responses\LogoutResponse;

class OauthLogoutResponse extends LogoutResponse
{
    public function toResponse($request)
    {
        $target = $request->query('redirect');
        $allowed = config('oauth_logout.return_urls', []);
        $parts = is_string($target) ? parse_url($target) : false;

        // JSON callers retain Fortify's 204 response. Browser handoffs may
        // return only to an exact configured application logout page.
        if ($request->wantsJson() || !is_array($parts) || !is_array($allowed)
            || !in_array($target, $allowed, true)
            || ($parts['scheme'] ?? null) !== 'https'
            || ($parts['path'] ?? null) !== '/logged-out'
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['port']) || isset($parts['query']) || isset($parts['fragment'])) {
            return parent::toResponse($request);
        }

        return $request->header('X-Inertia')
            ? Inertia::location($target)
            : redirect()->away($target);
    }
}
