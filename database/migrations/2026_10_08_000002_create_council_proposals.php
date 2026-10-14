<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('council_proposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('chair_id')->constrained('users');
            $table->string('title', 160);
            $table->text('objective');
            $table->json('executives');
            $table->json('electorate');
            $table->string('target')->nullable();
            $table->string('status')->default('open')->index();
            $table->json('decision')->nullable();
            $table->string('decision_hash', 64)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('council_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('proposal_id')->constrained('council_proposals')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('choice');
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['proposal_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('council_votes');
        Schema::dropIfExists('council_proposals');
    }
};
