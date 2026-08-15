<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_cohorts', function (Blueprint $table): void {
            // Set when the weekly finalisation has awarded/settled this cohort, so a
            // re-run of app:process-league-week skips it rather than double-granting.
            $table->timestamp('processed_at')->nullable()->after('week_start');
        });
    }

    public function down(): void
    {
        Schema::table('league_cohorts', function (Blueprint $table): void {
            $table->dropColumn('processed_at');
        });
    }
};
