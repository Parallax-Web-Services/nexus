<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Passport\Contracts\AuthorizationViewResponse as AuthorizationViewResponseContract;

class AuthorizationViewResponse implements AuthorizationViewResponseContract
{
    protected array $parameters = [];

    public function withParameters(array $parameters = []): static
    {
        $this->parameters = $parameters;
        return $this;
    }

    public function toResponse($request)
    {
        return response()->view('vendor.passport.authorize', [
            'client'  => $this->parameters['client'] ?? null,
            'user'    => $this->parameters['user'] ?? null,
            'scopes'  => $this->parameters['scopes'] ?? [],
            'request' => $this->parameters['request'] ?? $request,
        ]);
    }
}
