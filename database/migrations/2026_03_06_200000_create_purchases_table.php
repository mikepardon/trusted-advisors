<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('platform'); // 'stripe', 'apple', 'google'
            $table->string('product_id');
            $table->string('type'); // 'one_time', 'subscription'
            $table->integer('amount_cents')->default(0);
            $table->string('currency', 10)->default('usd');
            $table->string('transaction_id')->nullable();
            $table->text('receipt_data')->nullable();
            $table->string('status')->default('pending'); // 'pending', 'completed', 'refunded', 'failed'
            $table->foreignId('unlockable_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
