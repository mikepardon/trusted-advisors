<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->string('die1_theme_slug')->nullable()->after('dice');
            $table->string('die2_theme_slug')->nullable()->after('die1_theme_slug');
            $table->string('die3_theme_slug')->nullable()->after('die2_theme_slug');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['die1_theme_slug', 'die2_theme_slug', 'die3_theme_slug']);
        });
    }
};
