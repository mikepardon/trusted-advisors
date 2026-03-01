<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matchmaking_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('elo_rating');
            $table->integer('elo_range')->default(100);
            $table->integer('total_rounds');
            $table->string('status')->default('searching'); // searching, matched, cancelled
            $table->foreignId('matched_game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'elo_rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matchmaking_entries');
    }
};
