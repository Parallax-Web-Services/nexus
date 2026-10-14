<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RestrictLaunchPreview
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->user()?->token();
        if ($token && in_array('launch:preview', $token->scopes ?? [], true)) {
            abort_unless($request->isMethod('get') && $request->routeIs('launch.context'), 403);
        }
        return $next($request);
    }
}
