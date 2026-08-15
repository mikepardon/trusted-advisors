<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_cosmetics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cosmetic_id')->constrained()->cascadeOnDelete();
            $table->timestamp('unlocked_at');
            $table->timestamps();

            $table->unique(['user_id', 'cosmetic_id']);
            // Postgres does not auto-index foreign keys; the composite unique covers
            // user_id-leading lookups, so only cosmetic_id needs its own index.
            $table->index('cosmetic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_cosmetics');
    }
};
