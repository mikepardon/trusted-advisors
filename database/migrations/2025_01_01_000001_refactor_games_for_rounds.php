<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['current_year', 'current_month']);
            $table->integer('current_round')->default(0)->after('num_players');
            $table->integer('total_rounds')->default(20)->after('current_round');
            $table->string('round_phase')->default('dealing')->after('total_rounds');
            $table->boolean('win')->nullable()->after('round_phase');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['current_round', 'total_rounds', 'round_phase', 'win']);
            $table->integer('current_year')->default(245);
            $table->integer('current_month')->default(0);
        });
    }
};
