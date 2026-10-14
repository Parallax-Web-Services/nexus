<?php

namespace App\Support;

use Illuminate\Http\Request;

class AccountShell
{
    public function context(Request $request, mixed $activeService): array
    {
        $workspaces = [
            'continuum' => [
                'key' => 'continuum', 'name' => 'Continuum', 'role' => 'CEO',
                'mark' => '/assets/shell/continuum.svg',
                'home' => static::home(config('launch.continuum_url')),
                'description' => 'Direction, decisions, and your command deck.',
            ],
            'slipstream' => [
                'key' => 'slipstream', 'name' => 'Slipstream', 'role' => 'CTO',
                'mark' => '/assets/shell/slipstream.svg',
                'home' => static::home(config('nexus.services.slipstream.url')),
                'description' => 'Engineering, workstreams, and delivery.',
            ],
        ];

        // Only known keys are accepted. Destinations come from configuration,
        // never a client-provided return URL.
        $requested = $request->query('workspace');
        if (is_string($requested) && isset($workspaces[$requested])) {
            $request->session()->put('nexus.shell.workspace', $requested);
        }

        $service = is_array($activeService) ? ($activeService['name'] ?? null) : null;
        $selected = $request->session()->get('nexus.shell.workspace', $service);
        $key = is_string($selected) && isset($workspaces[$selected]) ? $selected : 'continuum';

        return $workspaces[$key] + ['workspace' => $key, 'workspaces' => array_values($workspaces)];
    }

    /** A workspace's dashboard, or this Nexus's own when it is not configured. */
    protected static function home(?string $url): string
    {
        return $url ? rtrim($url, '/').'/dashboard' : url('/dashboard');
    }
}
