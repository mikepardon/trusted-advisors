<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('timeout_count')->default(0)->after('max_login_streak');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->unsignedTinyInteger('timed_out_player_number')->nullable()->after('winner_player_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('timeout_count');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('timed_out_player_number');
        });
    }
};
