<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('launch_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('client_id', 100);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('fingerprint', 64);
            $table->json('brief');
            $table->boolean('created_user')->default(false);
            $table->char('claim_hash', 64)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->string('preview_token_id', 100)->nullable();
            $table->timestamp('preview_expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('council_trials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('services');
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('council_trials');
        Schema::dropIfExists('launch_requests');
    }
};
