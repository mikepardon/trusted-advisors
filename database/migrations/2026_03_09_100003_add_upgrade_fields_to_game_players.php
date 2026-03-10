<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->integer('card_redraw_uses')->default(0);
            $table->integer('card_redraws_used')->default(0);
            $table->integer('extra_item_slots')->default(0);
            $table->json('dice_overrides')->nullable();
            $table->json('passive_bonuses')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropColumn(['card_redraw_uses', 'card_redraws_used', 'extra_item_slots', 'dice_overrides', 'passive_bonuses']);
        });
    }
};
