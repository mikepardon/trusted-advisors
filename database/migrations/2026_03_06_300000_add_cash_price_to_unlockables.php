<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unlockables', function (Blueprint $table) {
            $table->integer('cash_price_cents')->nullable();
            $table->string('stripe_price_id')->nullable();
            $table->string('apple_product_id')->nullable();
            $table->string('google_product_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('unlockables', function (Blueprint $table) {
            $table->dropColumn([
                'cash_price_cents',
                'stripe_price_id',
                'apple_product_id',
                'google_product_id',
            ]);
        });
    }
};
