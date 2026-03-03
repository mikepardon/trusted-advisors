<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_challenges', function (Blueprint $table) {
            $table->id();
            $table->date('week_start')->unique();
            $table->date('week_end');
            $table->string('title');
            $table->text('description');
            $table->json('criteria');
            $table->integer('reward_xp')->default(300);
            $table->integer('reward_coins')->default(50);
            $table->boolean('is_manual')->default(false);
            $table->foreignId('addon_id')->nullable()->constrained('addons')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('weekly_challenge_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('weekly_challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('progress')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'weekly_challenge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_challenge_entries');
        Schema::dropIfExists('weekly_challenges');
    }
};
