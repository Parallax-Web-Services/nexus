<?php
namespace App\Http\Controllers;

use App\Models\CouncilTrial;
use App\Models\LaunchRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaunchClaimController
{
    public function __invoke(Request $request, LaunchRequest $launch)
    {
        // The route uses the standard auth + verified middleware. This controller
        // never authenticates an email address or changes verification status.
        abort_unless($request->user()?->id === $launch->user_id && $request->user()->hasVerifiedEmail(), 403);
        abort_unless($launch->claim_hash && hash_equals($launch->claim_hash, hash('sha256', (string) $request->query('token'))), 403);
        if ($request->isMethod('get')) {
            return view('launch.claim', ['launch' => $launch, 'days' => (int) config('launch.trial_days'), 'trial' => CouncilTrial::where('user_id', $launch->user_id)->first()]);
        }
        DB::transaction(function () use ($launch) {
            $locked = LaunchRequest::whereKey($launch->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->claimed_at || ! $locked->claim_hash, 409);
            $user = $locked->user()->lockForUpdate()->firstOrFail();
            CouncilTrial::firstOrCreate(['user_id' => $user->id], ['services' => config('launch.services'),
                'started_at' => now(), 'expires_at' => now()->addDays((int) config('launch.trial_days'))]);
            $locked->update(['claimed_at' => now(), 'claim_hash' => null]);
        });
        return redirect()->away(rtrim(config('launch.continuum_url'), '/').'/launch/'.$launch->id);
    }
}
