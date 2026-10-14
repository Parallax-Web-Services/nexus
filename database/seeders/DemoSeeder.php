<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Optional sample data; no users, credentials, or subscriptions are created.
     */
    public function run(): void
    {
        $this->call([
            DatabaseSeeder::class,
            PlanSeeder::class,
        ]);
    }
}
