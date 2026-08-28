<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_challenge_entries', function (Blueprint $table) {
            // Lifecycle of the single daily attempt: pending -> in_progress -> won|lost.
            $table->string('status')->default('pending')->after('game_id');
            $table->dateTime('started_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('daily_challenge_entries', function (Blueprint $table) {
            $table->dropColumn(['status', 'started_at']);
        });
    }
};
