<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->integer('wealth')->default(10)->change();
            $table->integer('influence')->default(10)->change();
            $table->integer('security')->default(10)->change();
            $table->integer('religion')->default(10)->change();
            $table->integer('food')->default(10)->change();
            $table->integer('happiness')->default(10)->change();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->integer('wealth')->default(15)->change();
            $table->integer('influence')->default(15)->change();
            $table->integer('security')->default(15)->change();
            $table->integer('religion')->default(15)->change();
            $table->integer('food')->default(15)->change();
            $table->integer('happiness')->default(15)->change();
        });
    }
};
