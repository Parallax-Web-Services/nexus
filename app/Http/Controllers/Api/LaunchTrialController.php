<?php
namespace App\Http\Controllers\Api;

use App\Actions\Fortify\CreateNewUser;
use App\Models\LaunchRequest;
use App\Models\CouncilTrial;
use App\Models\Passport\Client;
use App\Models\User;
use App\Notifications\LaunchTrialReady;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Registered;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Passport\Token;
use Throwable;

class LaunchTrialController extends Controller
{
    public function store(Request $request)
    {
        $credentials = $request->validate(['client_id' => 'required|string|max:100', 'client_secret' => 'required|string|max:255']);
        $client = Client::find($credentials['client_id']);
        $secret = $client?->getRawOriginal('secret');
        $valid = $secret && (str_starts_with($secret, '$') ? Hash::check($credentials['client_secret'], $secret) : hash_equals($secret, $credentials['client_secret']));
        abort_unless($client && ! $client->revoked && $client->confidential() && $valid
            && in_array(config('launch.trusted_callback'), $client->redirect_uris ?? [], true), 401);
        $input = $request->validate([
            'reference' => 'required|uuid', 'name' => 'required|string|max:120', 'email' => 'required|email:rfc|max:190',
            'trial_consented' => 'accepted', 'brief' => 'required|array:path,executive,summary,desired_outcome,context',
            'brief.path' => 'required|string|max:100', 'brief.executive' => 'nullable|in:continuum,slipstream,quark,plexus,positronic,pulsar',
            'brief.summary' => 'required|string|max:3000', 'brief.desired_outcome' => 'required|string|max:5000',
            'brief.context' => 'nullable|array|max:20', 'brief.context.*' => 'nullable|string|max:5000',
        ]);
        $email = Str::lower(trim($input['email']));
        $fingerprint = hash('sha256', json_encode([$email, $input['brief']], JSON_THROW_ON_ERROR));
        $launch = DB::transaction(function () use ($input, $email, $fingerprint, $client) {
            $existing = LaunchRequest::whereKey($input['reference'])->lockForUpdate()->first();
            if ($existing) {
                abort_unless($existing->client_id === (string) $client->id && hash_equals($existing->fingerprint, $fingerprint), 409);
                return $existing;
            }
            $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
            $created = ! $user;
            if (! $user) {
                $password = Str::random(64);
                $user = app(CreateNewUser::class)->create(['name' => $input['name'], 'email' => $email, 'password' => $password, 'password_confirmation' => $password, 'terms' => true]);
            }
            return LaunchRequest::create(['id' => $input['reference'], 'client_id' => (string) $client->id,
                'user_id' => $user->id, 'fingerprint' => $fingerprint, 'brief' => $input['brief'], 'created_user' => $created]);
        });
        $existingTrial = CouncilTrial::where('user_id', $launch->user_id)->first();
        if (! $launch->email_sent_at && ! $launch->claimed_at) {
            $claim = Str::random(64);
            $launch->update(['claim_hash' => hash('sha256', $claim)]);
            try {
                $setupUrl = null;
                if ($launch->created_user) {
                    // Reuse Fortify's password broker and verification notification.
                    // No login token or custom verification shortcut is created.
                    $setupUrl = route('password.reset', ['token' => Password::broker()->createToken($launch->user), 'email' => $launch->user->email]);
                    event(new Registered($launch->user));
                }
                $launch->user->notify(new LaunchTrialReady(URL::temporarySignedRoute('launch.claim', now()->addDay(), ['launch' => $launch->id, 'token' => $claim]), (int) config('launch.trial_days'), $setupUrl, (bool) $existingTrial));
                $launch->update(['email_sent_at' => now()]);
            } catch (Throwable $e) {
                Log::warning('Launch trial email unavailable.', ['reference' => $launch->id, 'exception' => $e::class]);
            }
        }
        // This token can read only this submitted brief. It cannot sign into an existing account.
        $token = $launch->user->createToken('Launch preview '.$launch->id, ['launch:preview']);
        if ($launch->preview_token_id) { Token::whereKey($launch->preview_token_id)->update(['revoked' => true]); }
        $launch->update(['preview_token_id' => $token->token->id, 'preview_expires_at' => now()->addMinutes(30)]);
        return response()->json(['reference' => $launch->id, 'access_token' => $token->accessToken,
            'trial' => ['state' => $existingTrial ? ($existingTrial->expires_at->isFuture() ? 'active' : 'expired') : 'ready', 'days' => (int) config('launch.trial_days'), 'expires_at' => $existingTrial?->expires_at?->toIso8601String(), 'email_sent' => (bool) $launch->email_sent_at],
            'workspace_url' => rtrim(config('launch.continuum_url'), '/').'/launch/'.$launch->id]);
    }

    public function context(Request $request, LaunchRequest $launch)
    {
        $token = $request->user()->token();
        abort_unless($request->user()->tokenCan('launch:preview') && $token && $token->id === $launch->preview_token_id
            && $request->user()->id === $launch->user_id && $launch->preview_expires_at?->isFuture(), 403);
        return response()->json(['reference' => $launch->id, 'subject' => (string) $launch->user_id, 'fingerprint' => $launch->fingerprint, 'brief' => $launch->brief]);
    }
}
