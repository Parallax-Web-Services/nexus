<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Maps a Nexus user to their Stripe customer on each non-Cashier billing
     * account. The primary (Parallax) account keeps using users.stripe_id.
     */
    public function up(): void
    {
        Schema::create('billing_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index();
            $table->string('billing_account');
            $table->string('stripe_customer_id');
            $table->timestamps();

            $table->unique(['user_id', 'billing_account']);
            $table->unique(['billing_account', 'stripe_customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_customers');
    }
};
