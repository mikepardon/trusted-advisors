<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dice_themes', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('data');
            $table->boolean('is_default_unlocked')->default(false)->after('is_active');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('active_dice_theme_slug')->nullable()->after('referral_code');
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['die1_theme_slug', 'die2_theme_slug', 'die3_theme_slug']);
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->string('die1_theme_slug')->nullable()->after('dice');
            $table->string('die2_theme_slug')->nullable()->after('die1_theme_slug');
            $table->string('die3_theme_slug')->nullable()->after('die2_theme_slug');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('active_dice_theme_slug');
        });

        Schema::table('dice_themes', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'is_default_unlocked']);
        });
    }
};
