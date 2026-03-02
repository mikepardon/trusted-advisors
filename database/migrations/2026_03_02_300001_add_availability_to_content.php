<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->boolean('available_cooperative')->default(true);
            $table->boolean('available_duel')->default(true);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->boolean('available_cooperative')->default(true);
            $table->boolean('available_duel')->default(true);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->boolean('available_cooperative')->default(true);
            $table->boolean('available_duel')->default(true);
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->boolean('available_cooperative')->default(true);
            $table->boolean('available_duel')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['available_cooperative', 'available_duel']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['available_cooperative', 'available_duel']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['available_cooperative', 'available_duel']);
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['available_cooperative', 'available_duel']);
        });
    }
};
