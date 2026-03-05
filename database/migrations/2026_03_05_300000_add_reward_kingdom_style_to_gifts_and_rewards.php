<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_gifts', function (Blueprint $table) {
            $table->unsignedBigInteger('reward_kingdom_style_id')->nullable()->after('reward_dice_theme_id');
            $table->foreign('reward_kingdom_style_id')->references('id')->on('kingdom_styles')->nullOnDelete();
        });

        Schema::table('season_rewards', function (Blueprint $table) {
            $table->unsignedBigInteger('reward_kingdom_style_id')->nullable()->after('reward_dice_theme_id');
            $table->foreign('reward_kingdom_style_id')->references('id')->on('kingdom_styles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admin_gifts', function (Blueprint $table) {
            $table->dropForeign(['reward_kingdom_style_id']);
            $table->dropColumn('reward_kingdom_style_id');
        });

        Schema::table('season_rewards', function (Blueprint $table) {
            $table->dropForeign(['reward_kingdom_style_id']);
            $table->dropColumn('reward_kingdom_style_id');
        });
    }
};