<?php

use App\Http\Controllers;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return to_route('login');
});

// Add get route for logout
// POST really isn't needed...
Route::get('logout', 'Laravel\Fortify\Http\Controllers\AuthenticatedSessionController@destroy');

// Webhooks for non-Cashier billing accounts; the primary account's webhook
// is Cashier's own POST /stripe/webhook.
Route::post('/stripe/webhook/{account}', [Controllers\StripeWebhookController::class, 'handle'])
    ->name('billing.webhook');

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::get('/dashboard', [Controllers\DashboardController::class, 'show'])->name('dashboard');

    Route::get('/billing', [Controllers\BillingController::class, 'show'])->name('billing.show');
    Route::get('/billing/portal/{account?}', [Controllers\BillingController::class, 'portal'])->name('billing.portal');
    Route::get('/billing/checkout/{plan}', [Controllers\BillingController::class, 'checkout'])->name('billing.checkout');
});

// Council review is public; purchases require Nexus identity, verification and CSRF.
Route::get('/council/checkout', [Controllers\CouncilCheckoutController::class, 'show'])->name('council.checkout');
Route::get('/council/health', fn () => response()->json(['service' => 'parallax-nexus-council', 'catalog_livemode' => config('council.catalog.livemode'), 'billing_livemode' => config('council.livemode')]))->middleware('throttle:30,1');
Route::post('/council/checkout', [Controllers\CouncilCheckoutController::class, 'checkout'])
    ->middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'throttle:10,1'])->name('council.purchase');
Route::post('/stripe/council/webhook', [Controllers\CouncilWebhookController::class, 'handle'])->name('council.webhook');
Route::get('/council/portal', [Controllers\CouncilCheckoutController::class, 'portal'])
    ->middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'throttle:10,1'])->name('council.portal');

Route::match(['GET', 'POST'], '/launch/claim/{launch}', \App\Http\Controllers\LaunchClaimController::class)->middleware(['signed', 'auth', 'verified', 'throttle:10,1'])->name('launch.claim');
