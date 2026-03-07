<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->integer('difficulty_duel')->nullable()->after('difficulty');
            $table->json('positive_effects_duel')->nullable()->after('positive_effects');
            $table->json('negative_effects_duel')->nullable()->after('negative_effects');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->json('effect_duel')->nullable()->after('effect');
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->json('dice_duel')->nullable()->after('dice');
            $table->integer('wild_value_duel')->nullable()->after('wild_value');
            $table->string('wild_ability_duel')->nullable()->after('wild_ability');
            $table->text('wild_ability_description_duel')->nullable()->after('wild_ability_description');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['difficulty_duel', 'positive_effects_duel', 'negative_effects_duel']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('effect_duel');
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['dice_duel', 'wild_value_duel', 'wild_ability_duel', 'wild_ability_description_duel']);
        });
    }
};
