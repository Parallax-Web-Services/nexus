<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect([
            'edit services',
            'delete services',
            'activate services',
            'deactivate services',
        ])->map(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'nexus',
        ]));

        foreach (['admin', 'super-admin'] as $name) {
            $role = Role::firstOrCreate([
                'name' => $name,
                'guard_name' => 'nexus',
            ]);

            $role->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
