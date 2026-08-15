<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cohort_id')->constrained('league_cohorts')->cascadeOnDelete();
            $table->date('week_start');
            $table->unsignedTinyInteger('tier');
            $table->unsignedTinyInteger('rank');
            $table->unsignedTinyInteger('total');
            $table->unsignedTinyInteger('tier_before');
            $table->unsignedTinyInteger('tier_after');
            $table->unsignedInteger('coins_earned')->default(0);
            // Null until the player has been shown the end-of-week overview on login.
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_results');
    }
};
