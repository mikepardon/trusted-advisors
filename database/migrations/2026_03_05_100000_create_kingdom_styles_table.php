<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kingdom_styles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->json('css_vars');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default_unlocked')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('active_kingdom_style_slug')->nullable()->after('active_dice_theme_slug');
            $table->string('active_title', 50)->nullable()->after('active_kingdom_style_slug');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['active_kingdom_style_slug', 'active_title']);
        });

        Schema::dropIfExists('kingdom_styles');
    }
};
