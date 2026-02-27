<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_item_deck', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->integer('position');
            $table->boolean('is_drawn')->default(false);
            $table->timestamps();
        });

        Schema::table('game_player_items', function (Blueprint $table) {
            $table->boolean('is_cursed')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_item_deck');

        Schema::table('game_player_items', function (Blueprint $table) {
            $table->dropColumn('is_cursed');
        });
    }
};
