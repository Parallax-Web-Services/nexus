<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use App\Support\Nexus;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected Request $request;
    protected ? Session $session;
    protected ? User $user;
    protected ? Team $team;
    protected Nexus $nexus;

    public function __construct(Nexus $nexus)
    {
        $this->nexus = $nexus;

        $this->middleware(function (Request $request, $next) {
            $this->request = $request;

            if ($request->hasSession()) {
                $this->session = $request->session();
            }

            $this->user = $request->user();

            if ($this->user) {
                $this->team = $this->user->teams()->first() ?: $this->user->personalTeam();

                // TODO:
                // Team::setCurrent($this->team);
            }

            return $next($request);
        });
    }
}
