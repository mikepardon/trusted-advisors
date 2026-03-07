<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->json('negative_effect');
            $table->json('positive_effect');
            $table->json('negative_effect_duel')->nullable();
            $table->json('positive_effect_duel')->nullable();
            $table->boolean('is_available')->default(true);
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        Schema::create('game_curse_deck', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curse_id')->constrained()->cascadeOnDelete();
            $table->integer('position');
            $table->boolean('is_drawn')->default(false);
            $table->timestamps();
        });

        Schema::create('game_player_curses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curse_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('acquired_round');
            $table->timestamps();
        });

        Schema::table('games', function (Blueprint $table) {
            $table->json('pending_curses')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('pending_curses');
        });

        Schema::dropIfExists('game_player_curses');
        Schema::dropIfExists('game_curse_deck');
        Schema::dropIfExists('curses');
    }
};
