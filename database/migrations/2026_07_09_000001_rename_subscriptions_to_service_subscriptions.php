<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Existing databases created the legacy Nexus entitlement table as
     * `subscriptions` before Cashier was introduced. Fresh installs create
     * `service_subscriptions` directly, so this only applies to old schemas.
     */
    public function up(): void
    {
        if (Schema::hasTable('subscriptions') && ! Schema::hasTable('service_subscriptions')) {
            Schema::rename('subscriptions', 'service_subscriptions');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('service_subscriptions') && ! Schema::hasTable('subscriptions')) {
            Schema::rename('service_subscriptions', 'subscriptions');
        }
    }
};
