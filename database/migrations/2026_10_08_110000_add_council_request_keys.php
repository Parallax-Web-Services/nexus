<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('council_proposals', function (Blueprint $table) { $table->string('request_key',64)->nullable(); $table->unique(['team_id','created_by','request_key'],'council_request_key_unique'); }); }
    public function down(): void { Schema::table('council_proposals', function (Blueprint $table) { $table->dropUnique('council_request_key_unique'); $table->dropColumn('request_key'); }); }
};
