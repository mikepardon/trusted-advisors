<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Marks a game as a Wordle-style deterministic daily-challenge run.
            $table->boolean('is_daily')->default(false)->after('is_custom');
            // The seed string all randomness for this run derives from.
            $table->string('daily_seed')->nullable()->after('is_daily');
            $table->foreignId('daily_challenge_id')->nullable()->after('daily_seed')
                ->index()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropConstrainedForeignId('daily_challenge_id');
            $table->dropColumn(['is_daily', 'daily_seed']);
        });
    }
};
