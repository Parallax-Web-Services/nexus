<?php

namespace App\Providers;

use App\Models\Cashier\Subscription;
use App\Models\Cashier\SubscriptionItem;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Passport\Contracts\AuthorizationViewResponse;
use App\Http\Responses\AuthorizationViewResponse as CustomAuthorizationViewResponse;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(AuthorizationViewResponse::class, CustomAuthorizationViewResponse::class);

        Cashier::useSubscriptionModel(Subscription::class);
        Cashier::useSubscriptionItemModel(SubscriptionItem::class);
    }
}
