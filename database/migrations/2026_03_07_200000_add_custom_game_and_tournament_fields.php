<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->boolean('is_custom')->default(false)->after('game_type');
            $table->json('custom_rules')->nullable()->after('is_custom');
            $table->boolean('is_private')->default(false)->after('custom_rules');
            $table->string('lobby_password')->nullable()->after('is_private');
            $table->unsignedBigInteger('tournament_match_id')->nullable()->after('lobby_password');
        });

        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('setup'); // setup, in_progress, completed
            $table->string('game_type')->default('duel');
            $table->integer('max_players');
            $table->integer('total_rounds')->default(24);
            $table->integer('current_bracket_round')->default(0);
            $table->json('custom_rules')->nullable();
            $table->boolean('is_private')->default(false);
            $table->string('lobby_password')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tournament_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('seed')->nullable();
            $table->timestamp('eliminated_at')->nullable();
            $table->integer('final_placement')->nullable();
            $table->timestamps();
            $table->unique(['tournament_id', 'user_id']);
        });

        Schema::create('tournament_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->integer('bracket_round');
            $table->integer('match_number');
            $table->unsignedBigInteger('player1_id')->nullable();
            $table->unsignedBigInteger('player2_id')->nullable();
            $table->unsignedBigInteger('winner_id')->nullable();
            $table->unsignedBigInteger('game_id')->nullable();
            $table->string('status')->default('pending'); // pending, in_progress, completed
            $table->timestamps();

            $table->foreign('player1_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('player2_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('winner_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('game_id')->references('id')->on('games')->nullOnDelete();
        });

        // Add FK after tournament_matches exists
        Schema::table('games', function (Blueprint $table) {
            $table->foreign('tournament_match_id')->references('id')->on('tournament_matches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropForeign(['tournament_match_id']);
            $table->dropColumn(['is_custom', 'custom_rules', 'is_private', 'lobby_password', 'tournament_match_id']);
        });

        Schema::dropIfExists('tournament_matches');
        Schema::dropIfExists('tournament_participants');
        Schema::dropIfExists('tournaments');
    }
};
