<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_unlockables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unlockable_id')->constrained()->cascadeOnDelete();
            $table->dateTime('unlocked_at');
            $table->timestamps();

            $table->unique(['user_id', 'unlockable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_unlockables');
    }
};
