<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('game_type')->default('cooperative'); // cooperative | duel
            $table->integer('offerer_player_number')->nullable();
            $table->string('duel_phase')->nullable(); // offering | choosing | rolling_offerer | rolling_chooser | resolving
            $table->integer('winner_player_number')->nullable();
        });

        Schema::table('game_player_hands', function (Blueprint $table) {
            $table->boolean('revealed')->default(false);
            $table->foreignId('offered_to_player_id')->nullable()->constrained('game_players')->nullOnDelete();
        });

        Schema::create('game_player_kingdoms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_player_id')->constrained('game_players')->cascadeOnDelete();
            $table->integer('wealth')->default(15);
            $table->integer('influence')->default(15);
            $table->integer('security')->default(15);
            $table->integer('religion')->default(15);
            $table->integer('food')->default(15);
            $table->integer('happiness')->default(15);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_player_kingdoms');

        Schema::table('game_player_hands', function (Blueprint $table) {
            $table->dropConstrainedForeignId('offered_to_player_id');
            $table->dropColumn('revealed');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['game_type', 'offerer_player_number', 'duel_phase', 'winner_player_number']);
        });
    }
};
