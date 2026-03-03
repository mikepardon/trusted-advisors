<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matchmaking_entries', function (Blueprint $table) {
            $table->string('speed_mode', 10)->default('speed')->after('total_rounds');
        });
    }

    public function down(): void
    {
        Schema::table('matchmaking_entries', function (Blueprint $table) {
            $table->dropColumn('speed_mode');
        });
    }
};
