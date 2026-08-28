<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->index()->constrained()->cascadeOnDelete();
            // Whether this item is in the player's ready loadout (max 3, enforced in the app layer).
            $table->boolean('equipped')->default(false);
            // Optional loadout slot ordering (1-3).
            $table->unsignedTinyInteger('slot')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_items');
    }
};
