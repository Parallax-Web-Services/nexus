<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMetricsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id');
            $table->foreignId('team_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->string('event_type');
            $table->text('event_data')->nullable();
            $table->timestamp('occurred_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('metrics');
    }
}
