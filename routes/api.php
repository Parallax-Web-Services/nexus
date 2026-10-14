<?php

use App\Http\Controllers;
use App\Models\User;
use App\Support\CouncilEntitlements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/launch/trials', [Controllers\Api\LaunchTrialController::class, 'store'])->middleware('throttle:60,1');

Route::middleware(['auth:api', \App\Http\Middleware\RestrictLaunchPreview::class])->group(function () {
    Route::get('/launch/{launch}/context', [Controllers\Api\LaunchTrialController::class, 'context'])->name('launch.context');
    Route::get('/v1/identity', [Controllers\Api\IdentityController::class, 'show']);
    Route::delete('/v1/session', [Controllers\Api\IdentityController::class, 'destroy']);

    Route::get('/me', function (Request $request) {
        return $request->user()->makeHidden(User::HIDDEN_FROM_CLIENTS)->toArray()
            + ['service_entitlements' => app(CouncilEntitlements::class)->services($request->user())];
    });

    Route::prefix('council')->middleware('throttle:60,1')->group(function () {
        Route::get('/boardroom', [Controllers\Api\CouncilBoardroomController::class, 'index']);
        Route::post('/proposals', [Controllers\Api\CouncilBoardroomController::class, 'store']);
        Route::post('/proposals/{proposal}/votes', [Controllers\Api\CouncilBoardroomController::class, 'vote']);
        Route::post('/proposals/{proposal}/close', [Controllers\Api\CouncilBoardroomController::class, 'close']);
        Route::get('/proposals/{proposal}/handoff', [Controllers\Api\CouncilBoardroomController::class, 'handoff']);
    });

    Route::any('{route}', [Controllers\ApiForwarderController::class, 'handle'])
        ->where('route', '.*');
});
