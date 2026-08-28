<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_player_items', function (Blueprint $table) {
            // Tracks per-round usage for per_round cadence items. `is_used` now means
            // "permanently spent" (per_game cadence); `used_round` records the last round used.
            $table->unsignedInteger('uses_this_round')->default(0)->after('used_round');
        });
    }

    public function down(): void
    {
        Schema::table('game_player_items', function (Blueprint $table) {
            $table->dropColumn('uses_this_round');
        });
    }
};
