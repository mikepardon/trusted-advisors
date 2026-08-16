<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            // Set the first time completion rewards are granted, so a completion path
            // firing more than once (both players resolving, bot + human, a retried
            // request/job) can't award XP/coins/points twice.
            $table->timestamp('rewards_processed_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn('rewards_processed_at');
        });
    }
};
