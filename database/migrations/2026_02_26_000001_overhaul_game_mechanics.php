<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Characters table
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('has_starting_card');
            $table->integer('wild_value')->default(3);
            $table->string('wild_ability')->default('reroll');
            $table->text('wild_ability_description')->nullable();
        });

        // Cards table
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['difficulty', 'requirements', 'success_effects', 'failure_effects']);
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->integer('difficulty')->default(6);
            $table->json('positive_effects')->nullable();
            $table->json('negative_effects')->nullable();
        });

        // Items table
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('effect');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->string('effect_type')->default('passive');
            $table->json('effect')->nullable();
            $table->boolean('is_negative')->default(false);
            $table->boolean('is_consumable')->default(false);
        });

        // game_player_hands table
        Schema::table('game_player_hands', function (Blueprint $table) {
            $table->dropColumn(['is_selected', 'is_discarded']);
            $table->string('role')->nullable();
        });

        // game_round_results table
        Schema::table('game_round_results', function (Blueprint $table) {
            $table->string('result_type')->default('positive');
            $table->json('cards_included')->nullable();
            $table->json('wild_triggers')->nullable();
        });

        // Make card_id and game_player_id nullable on game_round_results
        Schema::table('game_round_results', function (Blueprint $table) {
            $table->unsignedBigInteger('card_id')->nullable()->change();
            $table->unsignedBigInteger('game_player_id')->nullable()->change();
        });

        // game_player_items table
        Schema::table('game_player_items', function (Blueprint $table) {
            $table->boolean('is_used')->default(false);
            $table->integer('acquired_round')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('game_player_items', function (Blueprint $table) {
            $table->dropColumn(['is_used', 'acquired_round']);
        });

        Schema::table('game_round_results', function (Blueprint $table) {
            $table->dropColumn(['result_type', 'cards_included', 'wild_triggers']);
        });

        Schema::table('game_player_hands', function (Blueprint $table) {
            $table->dropColumn('role');
            $table->boolean('is_selected')->default(false);
            $table->boolean('is_discarded')->default(false);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['effect_type', 'effect', 'is_negative', 'is_consumable']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->json('effect')->nullable();
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['difficulty', 'positive_effects', 'negative_effects']);
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->string('difficulty')->default('medium');
            $table->json('requirements')->nullable();
            $table->json('success_effects')->nullable();
            $table->json('failure_effects')->nullable();
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['wild_value', 'wild_ability', 'wild_ability_description']);
            $table->boolean('has_starting_card')->default(false);
        });
    }
};
