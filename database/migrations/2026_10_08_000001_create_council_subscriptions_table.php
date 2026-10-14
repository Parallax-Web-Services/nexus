<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('council_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('billing_account');
            $table->string('stripe_subscription_id');
            $table->string('stripe_customer_id');
            $table->string('status');
            $table->json('price_ids');
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('event_created')->default(0);
            $table->timestamps();
            $table->unique(['billing_account', 'stripe_subscription_id'], 'council_subscription_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('council_subscriptions');
    }
};
