<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unlockables', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'character' or 'item'
            $table->unsignedBigInteger('entity_id');
            $table->string('unlock_method'); // 'level' or 'achievement'
            $table->integer('unlock_value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unlockables');
    }
};
