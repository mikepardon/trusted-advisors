<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->integer('lost_dice')->default(0);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('mechanic')->nullable();
            $table->json('mechanic_data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropColumn('lost_dice');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['mechanic', 'mechanic_data']);
        });
    }
};
