<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_challenge_entries', function (Blueprint $table) {
            // Months (rounds) taken to reach the target on a won endless run.
            $table->unsignedInteger('rounds_taken')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('daily_challenge_entries', function (Blueprint $table) {
            $table->dropColumn('rounds_taken');
        });
    }
};
