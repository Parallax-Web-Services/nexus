<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use App\Http\Responses\OauthLogoutResponse;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(LogoutResponse::class, OauthLogoutResponse::class);

        $this->app->instance(LoginResponse::class, new class implements LoginResponse {
            public function toResponse($request)
            {
                if ($request->inertia()) {
                    return Inertia::location(
                        redirect()->intended(Fortify::redirects('login'))->getTargetUrl()
                    );
                }

                return $request->wantsJson()
                    ? response()->json(['two_factor' => false])
                    : redirect()->intended(Fortify::redirects('login'));
            }
        });

        // Registration handoffs can resume at Passport's authorize endpoint,
        // which immediately redirects to the service's callback. An Inertia
        // XHR cannot follow that cross-origin hop, so force a full page visit.
        $this->app->instance(RegisterResponse::class, new class implements RegisterResponse {
            public function toResponse($request)
            {
                if ($request->inertia()) {
                    return Inertia::location(
                        redirect()->intended(Fortify::redirects('register'))->getTargetUrl()
                    );
                }

                return $request->wantsJson()
                    ? new JsonResponse('', 201)
                    : redirect()->intended(Fortify::redirects('register'));
            }
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::verifyEmailView(function (Request $request) {
            return Inertia::render('Auth/VerifyEmail', [
                'status' => $request->session()->get('status'),
                'email' => $request->user()->getEmailForVerification(),
            ]);
        });

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->email;

            return Limit::perMinute(5)->by($email.$request->ip());
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
