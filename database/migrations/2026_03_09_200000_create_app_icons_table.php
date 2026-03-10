<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_icons', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('category');
            $table->string('icon_type')->default('emoji');
            $table->string('icon_value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_icons');
    }
};
