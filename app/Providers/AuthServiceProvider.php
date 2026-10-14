<?php

namespace App\Providers;

use App\Models\Passport\Client;
use App\Models\Team;
use App\Policies\TeamPolicy;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        Team::class => TeamPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Passport raises this exception directly when an OAuth browser guest
        // needs to sign in; Laravel 13 requires an explicit redirect callback.
        AuthenticationException::redirectUsing(fn () => route('login'));

        // override client model to auto approve
        // oauth requests for first party clients
        Passport::useClientModel(Client::class);
        if (config('passport.key_path')) {
            Passport::loadKeysFrom(config('passport.key_path'));
        }

        Passport::tokensCan([
            'full-access' => 'Full access',
            'launch:preview' => 'Prepare a first answer from a submitted launch brief',
            'identity:read' => 'Read your identity',
            'council:read' => 'Read your council proposals and decisions',
            'council:write' => 'Create proposals and cast council votes',
            'create' => 'Create resources',
            'read' => 'Read resources',
            'update' => 'Update resources',
            'delete' => 'Delete resources',
        ]);
    }
}
