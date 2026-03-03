<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_gifts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('note')->nullable();
            $table->integer('reward_xp')->default(0);
            $table->integer('reward_coins')->default(0);
            $table->foreignId('reward_character_id')->nullable()->constrained('characters')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->integer('recipient_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_gifts');
    }
};
