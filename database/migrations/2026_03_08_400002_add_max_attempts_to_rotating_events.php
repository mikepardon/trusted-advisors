<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rotating_events', function (Blueprint $table) {
            $table->integer('max_attempts')->nullable()->after('reward_coins'); // null = unlimited
        });
    }

    public function down(): void
    {
        Schema::table('rotating_events', function (Blueprint $table) {
            $table->dropColumn('max_attempts');
        });
    }
};
