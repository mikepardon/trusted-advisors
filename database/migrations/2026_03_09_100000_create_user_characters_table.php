<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->integer('xp')->default(0);
            $table->integer('level')->default(1);
            $table->integer('incarnation')->default(1);
            $table->string('incarnation_name')->nullable();
            $table->integer('max_item_slots_bonus')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'character_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_characters');
    }
};
