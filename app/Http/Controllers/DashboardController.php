<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class DashboardController extends Controller
{
    public function show()
    {
        $service = $this->nexus->service();
        if ($service && ! in_array($service['name'], ['continuum', 'slipstream'], true)) {
            return Inertia::location($service['url']);
        }

        return Inertia::render('Dashboard');
    }
}
