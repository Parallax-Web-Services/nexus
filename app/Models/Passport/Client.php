<?php

namespace App\Models\Passport;

use Laravel\Passport\Client as BaseClient;
use Illuminate\Contracts\Auth\Authenticatable;

class Client extends BaseClient
{
    /**
     * Determine if the client should skip the authorization prompt.
     *
     * @return bool
     */

    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return $this->user_id === null;
    }
}
