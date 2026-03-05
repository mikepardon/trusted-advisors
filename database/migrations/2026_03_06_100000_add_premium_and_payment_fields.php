<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_premium')->default(false);
            $table->string('premium_platform')->nullable(); // 'stripe', 'apple', 'google'
            $table->string('premium_transaction_id')->nullable();
            $table->timestamp('premium_expires_at')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->timestamp('app_review_prompted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_premium',
                'premium_platform',
                'premium_transaction_id',
                'premium_expires_at',
                'stripe_customer_id',
                'app_review_prompted_at',
            ]);
        });
    }
};
