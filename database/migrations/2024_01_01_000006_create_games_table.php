<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('setup');
            $table->integer('num_players');
            $table->integer('current_year')->default(245);
            $table->integer('current_month')->default(0);
            $table->integer('wealth')->default(15);
            $table->integer('influence')->default(15);
            $table->integer('security')->default(15);
            $table->integer('religion')->default(15);
            $table->integer('food')->default(15);
            $table->integer('happiness')->default(15);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
