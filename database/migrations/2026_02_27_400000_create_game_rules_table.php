<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_rules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });

        // Seed default dice-per-player-count rules
        DB::table('game_rules')->insert([
            'key' => 'dice_per_player_count',
            'value' => json_encode([
                '1' => 3,
                '2' => 3,
                '3' => 3,
                '4' => 3,
                '5' => 3,
                '6' => 3,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('game_rules');
    }
};
