<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_challenges', function (Blueprint $table): void {
            // Cached results of the balancing simulation (150 bot playthroughs of the
            // identical seeded scenario a human faces). Null until the sim has been run.
            $table->unsignedSmallInteger('sim_runs')->nullable()->after('reward_xp');
            $table->unsignedTinyInteger('sim_success_rate')->nullable()->after('sim_runs');
            $table->decimal('sim_avg_months', 4, 1)->nullable()->after('sim_success_rate');
            $table->timestamp('sim_computed_at')->nullable()->after('sim_avg_months');
        });
    }

    public function down(): void
    {
        Schema::table('daily_challenges', function (Blueprint $table): void {
            $table->dropColumn(['sim_runs', 'sim_success_rate', 'sim_avg_months', 'sim_computed_at']);
        });
    }
};
