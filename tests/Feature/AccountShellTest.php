<?php

namespace Tests\Feature;

use App\Support\AccountShell;
use Illuminate\Http\Request;
use Tests\TestCase;

class AccountShellTest extends TestCase
{
    private function request(array $query = []): Request
    {
        $request = Request::create('/dashboard', 'GET', $query);
        $request->setLaravelSession(app('session.store'));

        return $request;
    }

    public function test_central_account_defaults_to_continuum_with_configured_destinations(): void
    {
        config(['launch.continuum_url' => 'https://continuum.test/', 'nexus.services.slipstream.url' => 'https://slipstream.test/']);
        $shell = app(AccountShell::class)->context($this->request(), false);
        $this->assertSame('continuum', $shell['workspace']);
        $this->assertSame('https://continuum.test/dashboard', $shell['home']);
        $this->assertSame('https://slipstream.test/dashboard', $shell['workspaces'][1]['home']);
    }

    public function test_handoff_persists_workspace_across_account_pages(): void
    {
        $shell = app(AccountShell::class);
        $this->assertSame('slipstream', $shell->context($this->request(['workspace' => 'slipstream']), false)['workspace']);
        $this->assertSame('slipstream', $shell->context($this->request(), false)['workspace']);
        $this->assertSame('continuum', $shell->context($this->request(['workspace' => 'continuum']), false)['workspace']);
    }

    public function test_invalid_workspace_and_return_urls_cannot_change_the_destination(): void
    {
        config(['launch.continuum_url' => 'https://continuum.test']);
        $shell = app(AccountShell::class);
        foreach (['https://untrusted.test', ['slipstream']] as $invalid) {
            $context = $shell->context($this->request(['workspace' => $invalid, 'return_to' => 'https://untrusted.test']), false);
            $this->assertSame('continuum', $context['workspace']);
            $this->assertSame('https://continuum.test/dashboard', $context['home']);
        }
    }

    public function test_slipstream_host_selects_its_workspace_on_first_visit(): void
    {
        $context = app(AccountShell::class)->context($this->request(), ['name' => 'slipstream']);
        $this->assertSame('slipstream', $context['workspace']);
    }
}
