<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Service;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Add an optional example catalog without enabling billing or access.
     */
    public function run(): void
    {
        $service = Service::firstOrCreate(
            ['key' => 'example-app'],
            ['name' => 'Example App']
        );

        $plan = Plan::firstOrCreate([
            'name' => 'Example Plan',
            'billing_account' => 'demo',
        ]);

        $plan->services()->syncWithoutDetaching([$service->id]);
    }
}
