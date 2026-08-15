<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_pass_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('tier');
            $table->unsignedInteger('points_required');
            $table->unsignedInteger('reward_coins')->default(0);
            $table->foreignId('reward_cosmetic_id')->nullable()->constrained('cosmetics')->nullOnDelete();
            $table->string('name')->nullable();
            $table->timestamps();

            $table->unique(['season_id', 'tier']);
            $table->index('reward_cosmetic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_pass_tiers');
    }
};
