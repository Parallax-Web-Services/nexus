<?php

namespace App\Http\Middleware;

use App\Facades\Nexus;
use App\Support\AccountShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     */
    public function share(Request $request): array
    {
        $activeService = Nexus::service();

        return array_merge(parent::share($request), [
            'user' => Auth::user(),
            'activeService' => $activeService,
            'shell' => app(AccountShell::class)->context($request, $activeService),
            'registration' => $this->registrationContext($request, $activeService),
        ]);
    }

    /**
     * Capture a service-owned registration handoff and preserve its return
     * through Fortify's default redirect()->intended() response.
     */
    protected function registrationContext(Request $request, $activeService): ?array
    {
        if (! $request->isMethod('get') || ! $request->routeIs('register') || ! $activeService) {
            return null;
        }

        $returnTo = $this->trustedServiceReturnTo(
            $request,
            (string) $request->query('return_to'),
            (string) ($activeService['url'] ?? ''),
        );

        if (! $returnTo) {
            return null;
        }

        $request->session()->put('url.intended', $returnTo);

        $email = Str::lower(trim((string) $request->query('email')));

        return [
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
            'service' => $activeService['name'],
        ];
    }

    /**
     * Only the active service may supply the post-registration destination.
     * This keeps the handoff useful without turning Nexus into an open redirect.
     */
    protected function trustedServiceReturnTo(Request $request, string $returnTo, string $serviceUrl): ?string
    {
        $target = parse_url($returnTo);
        $service = parse_url($serviceUrl);

        if (! is_array($target) || ! is_array($service)) {
            return null;
        }

        if ($this->sameOrigin($target, $service)) {
            return $returnTo;
        }

        // New service users resume directly inside Nexus at Passport's OAuth
        // endpoint. Passport validates the client; we additionally require its
        // callback to belong to the active service.
        $nexus = [
            'scheme' => $service['scheme'] ?? null,
            'host' => $request->getHost(),
        ];
        parse_str($target['query'] ?? '', $oauth);
        $redirect = parse_url((string) ($oauth['redirect_uri'] ?? ''));

        if (
            ($target['path'] ?? '') !== '/oauth/authorize'
            || ! is_array($redirect)
            || ! $this->sameOrigin($target, $nexus)
            || ! $this->sameOrigin($redirect, $service)
        ) {
            return null;
        }

        return $returnTo;
    }

    protected function sameOrigin(array $left, array $right): bool
    {
        if (
            empty($left['scheme'])
            || empty($left['host'])
            || empty($right['scheme'])
            || empty($right['host'])
        ) {
            return false;
        }

        $leftScheme = Str::lower($left['scheme']);
        $rightScheme = Str::lower($right['scheme']);

        return in_array($leftScheme, ['http', 'https'], true)
            && $leftScheme === $rightScheme
            && hash_equals(Str::lower($right['host']), Str::lower($left['host']))
            && $this->urlPort($left) === $this->urlPort($right);
    }

    protected function urlPort(array $url): int
    {
        return (int) ($url['port'] ?? ($url['scheme'] === 'https' ? 443 : 80));
    }
}
