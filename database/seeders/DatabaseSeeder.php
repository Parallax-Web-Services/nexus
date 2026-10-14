<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Install core records without changing existing application data.
     */
    public function run(): void
    {
        $this->call([
            ServiceSeeder::class,
            RolesAndPermissionsSeeder::class,
        ]);
    }
}
