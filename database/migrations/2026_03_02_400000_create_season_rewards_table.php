<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->integer('placement');
            $table->integer('reward_xp')->default(0);
            $table->integer('reward_coins')->default(0);
            $table->foreignId('reward_character_id')->nullable()->constrained('characters')->nullOnDelete();
            $table->string('reward_title')->nullable();
            $table->timestamps();
            $table->unique(['season_id', 'placement']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_rewards');
    }
};
