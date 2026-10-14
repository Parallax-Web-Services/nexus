<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        Service::firstOrCreate(
            ['key' => 'nexus'],
            ['name' => 'Nexus']
        );
    }
}
