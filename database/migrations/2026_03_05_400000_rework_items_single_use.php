<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_player_items', function (Blueprint $table) {
            $table->unsignedInteger('used_round')->nullable()->after('is_used');
        });

        Schema::table('game_players', function (Blueprint $table) {
            $table->boolean('item_decided')->default(false)->after('ability_active_this_round');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->string('target')->nullable()->default(null)->after('available_duel');
        });
    }

    public function down(): void
    {
        Schema::table('game_player_items', function (Blueprint $table) {
            $table->dropColumn('used_round');
        });

        Schema::table('game_players', function (Blueprint $table) {
            $table->dropColumn('item_decided');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('target');
        });
    }
};
