<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseSeedingTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeding_is_repeatable_without_users_clients_or_private_service_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('services', 1);
        $this->assertDatabaseHas('services', ['key' => 'nexus']);
        $this->assertDatabaseCount('permissions', 4);
        $this->assertDatabaseCount('roles', 2);
        $this->assertDatabaseCount('role_has_permissions', 8);

        foreach (['users', 'teams', 'oauth_clients', 'plans', 'plan_service', 'service_subscriptions'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_default_seeding_preserves_existing_records_credentials_and_grants(): void
    {
        $service = Service::create(['key' => 'nexus', 'name' => 'Customized Nexus']);
        $privateService = Service::create(['key' => 'private-app', 'name' => 'Private App']);
        $plan = Plan::create(['name' => 'Existing Plan', 'billing_account' => 'custom']);
        $plan->services()->attach([$service->id, $privateService->id]);

        $user = User::factory()->withPersonalTeam()->create();
        $passwordHash = $user->getRawOriginal('password');
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'Existing Client', ['https://example.test/oauth/callback']
        );
        $clientSecret = $client->getRawOriginal('secret');

        $permission = Permission::create(['name' => 'custom permission', 'guard_name' => 'nexus']);
        $role = Role::create(['name' => 'admin', 'guard_name' => 'nexus']);
        $role->givePermissionTo($permission);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame('Customized Nexus', $service->fresh()->name);
        $this->assertDatabaseHas('services', ['id' => $privateService->id, 'key' => 'private-app']);
        $this->assertSame([$service->id, $privateService->id], $plan->services()->orderBy('services.id')->pluck('services.id')->all());
        $this->assertDatabaseCount('plans', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('teams', 1);
        $this->assertDatabaseCount('oauth_clients', 1);
        $this->assertSame($passwordHash, $user->fresh()->getRawOriginal('password'));
        $this->assertSame($clientSecret, $client->fresh()->getRawOriginal('secret'));
        $this->assertTrue($role->fresh()->hasPermissionTo('custom permission'));
        $this->assertTrue($role->fresh()->hasPermissionTo('edit services'));
    }

    public function test_optional_demo_seeding_is_repeatable_and_does_not_grant_or_enable_paid_access(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertDatabaseCount('services', 2);
        $this->assertDatabaseCount('plans', 1);
        $this->assertDatabaseCount('plan_service', 1);
        $this->assertDatabaseHas('services', ['key' => 'example-app', 'name' => 'Example App']);
        $this->assertDatabaseHas('plans', [
            'name' => 'Example Plan',
            'billing_account' => 'demo',
            'stripe_price_id' => null,
        ]);

        foreach (['users', 'oauth_clients', 'service_subscriptions', 'cashier_subscriptions', 'billing_customers'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_optional_demo_seeding_preserves_existing_plan_service_links(): void
    {
        $this->seed(DemoSeeder::class);

        $plan = Plan::where('billing_account', 'demo')->sole();
        $service = Service::create(['key' => 'extra-app', 'name' => 'Extra App']);
        $plan->services()->attach($service);

        $this->seed(DemoSeeder::class);

        $this->assertCount(2, $plan->fresh()->services);
        $this->assertTrue($plan->fresh()->services->contains($service));
    }
}
