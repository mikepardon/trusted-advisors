<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_character_upgrades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_level_option_id')->constrained()->cascadeOnDelete();
            $table->integer('chosen_at_level');
            $table->integer('incarnation');
            $table->json('user_choice')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_character_upgrades');
    }
};
