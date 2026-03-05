<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('season_rewards', function (Blueprint $table) {
            $table->unsignedBigInteger('reward_dice_theme_id')->nullable()->after('reward_character_id');
            $table->foreign('reward_dice_theme_id')->references('id')->on('dice_themes')->nullOnDelete();
        });

        Schema::table('admin_gifts', function (Blueprint $table) {
            $table->unsignedBigInteger('reward_dice_theme_id')->nullable()->after('reward_character_id');
            $table->foreign('reward_dice_theme_id')->references('id')->on('dice_themes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('season_rewards', function (Blueprint $table) {
            $table->dropForeign(['reward_dice_theme_id']);
            $table->dropColumn('reward_dice_theme_id');
        });

        Schema::table('admin_gifts', function (Blueprint $table) {
            $table->dropForeign(['reward_dice_theme_id']);
            $table->dropColumn('reward_dice_theme_id');
        });
    }
};
