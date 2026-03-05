<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create payment_customers table
        Schema::create('payment_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform'); // 'stripe', 'apple', 'google'
            $table->string('platform_customer_id');
            $table->timestamps();

            $table->unique(['user_id', 'platform']);
            $table->index('platform_customer_id');
        });

        // 2. Create user_subscriptions table
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform'); // 'stripe', 'apple', 'google', 'admin'
            $table->string('subscription_id')->nullable();
            $table->string('status'); // 'active', 'canceled', 'past_due', 'expired'
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->string('plan_interval')->nullable(); // 'month', 'year'
            $table->integer('plan_interval_count')->default(1);
            $table->integer('plan_amount_cents')->nullable();
            $table->string('plan_currency', 10)->default('usd');
            $table->timestamps();
        });

        // 3. Migrate existing data
        // Move stripe_customer_id to payment_customers
        $usersWithStripe = DB::table('users')
            ->whereNotNull('stripe_customer_id')
            ->select('id', 'stripe_customer_id')
            ->get();

        foreach ($usersWithStripe as $user) {
            DB::table('payment_customers')->insert([
                'user_id' => $user->id,
                'platform' => 'stripe',
                'platform_customer_id' => $user->stripe_customer_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Move active premium users to user_subscriptions
        $premiumUsers = DB::table('users')
            ->where('is_premium', true)
            ->select('id', 'premium_platform', 'premium_transaction_id', 'premium_expires_at')
            ->get();

        foreach ($premiumUsers as $user) {
            DB::table('user_subscriptions')->insert([
                'user_id' => $user->id,
                'platform' => $user->premium_platform ?? 'admin',
                'subscription_id' => $user->premium_transaction_id,
                'status' => 'active',
                'current_period_end' => $user->premium_expires_at,
                'cancel_at_period_end' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Drop old columns from users
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['premium_platform', 'premium_transaction_id', 'stripe_customer_id']);
        });
    }

    public function down(): void
    {
        // Re-add dropped columns
        Schema::table('users', function (Blueprint $table) {
            $table->string('premium_platform')->nullable();
            $table->string('premium_transaction_id')->nullable();
            $table->string('stripe_customer_id')->nullable();
        });

        // Migrate data back
        $subscriptions = DB::table('user_subscriptions')->get();
        foreach ($subscriptions as $sub) {
            DB::table('users')->where('id', $sub->user_id)->update([
                'premium_platform' => $sub->platform,
                'premium_transaction_id' => $sub->subscription_id,
            ]);
        }

        $customers = DB::table('payment_customers')
            ->where('platform', 'stripe')
            ->get();
        foreach ($customers as $cust) {
            DB::table('users')->where('id', $cust->user_id)->update([
                'stripe_customer_id' => $cust->platform_customer_id,
            ]);
        }

        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('payment_customers');
    }
};
