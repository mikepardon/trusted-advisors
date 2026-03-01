<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_round_results', function (Blueprint $table) {
            $table->json('special_effects')->nullable()->after('wild_triggers');
            $table->json('kingdom_snapshot')->nullable()->after('special_effects');
            $table->json('event_data')->nullable()->after('kingdom_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('game_round_results', function (Blueprint $table) {
            $table->dropColumn(['special_effects', 'kingdom_snapshot', 'event_data']);
        });
    }
};
