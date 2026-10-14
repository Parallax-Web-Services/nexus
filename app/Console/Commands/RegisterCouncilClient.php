<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

class RegisterCouncilClient extends Command
{
    protected $signature = 'council:register-client {application : continuum, boardroom, quark or pulsar}';

    protected $description = 'Register the configured first-party callback and privately export its OAuth credentials';

    public function handle(ClientRepository $clients): int
    {
        $application = $this->argument('application');
        $callback = config('council.clients.'.$application);
        if (! in_array($application, ['continuum', 'boardroom', 'quark', 'pulsar'], true) || ! filter_var($callback, FILTER_VALIDATE_URL)
            || (! str_starts_with($callback, 'https://') && ! ($this->laravel->environment('local', 'testing', 'council-preview') && str_starts_with($callback, 'http://127.0.0.1:')))) {
            $this->error('Configure a valid HTTPS council callback (loopback allowed in local environments).');

            return self::FAILURE;
        }
        $directory = storage_path('app/private/council');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $path = $directory.'/'.$application.'.env';
        if (is_file($path)) {
            $values = parse_ini_file($path, false, INI_SCANNER_RAW);
            $client = Passport::client()->find($values['OAUTH_CLIENT_ID'] ?? null);
            if (! $client || $client->revoked || ! in_array($callback, $client->redirect_uris, true)) {
                $this->error('The private export does not match this callback. Review it before registering another client.');

                return self::FAILURE;
            }
            $this->info('Existing client verified. Private export: '.$path);

            return self::SUCCESS;
        }
        if (! is_writable($directory)) {
            $this->error('Private export directory must be writable.');

            return self::FAILURE;
        }
        $client = $clients->createAuthorizationCodeGrantClient('Parallax council / '.$application, [$callback]);
        file_put_contents($path, 'OAUTH_CLIENT_ID='.$client->id."\nOAUTH_CLIENT_SECRET=".$client->plainSecret."\n", LOCK_EX);
        chmod($path, 0600);
        $this->info('Client registered. Credentials saved privately to '.$path);

        return self::SUCCESS;
    }
}
