<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->unsignedInteger('tier')->default(1)->after('criteria');
            $table->string('tier_group')->nullable()->after('tier');
            $table->unsignedInteger('reward_xp')->default(0)->after('tier_group');
        });
    }

    public function down(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->dropColumn(['tier', 'tier_group', 'reward_xp']);
        });
    }
};
