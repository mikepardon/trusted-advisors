<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_pass_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('points')->default(0);
            $table->json('claimed_tiers')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'season_id']);
            $table->index('season_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_pass_progress');
    }
};
