<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->integer('turn_time_limit')->nullable()->after('total_rounds');
            $table->timestamp('turn_started_at')->nullable()->after('turn_time_limit');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['turn_time_limit', 'turn_started_at']);
        });
    }
};
