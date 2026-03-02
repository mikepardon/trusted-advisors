<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('season_rewards', function (Blueprint $table) {
            $table->string('metric', 20)->default('elo')->after('season_id');

            $table->dropUnique(['season_id', 'placement']);
            $table->unique(['season_id', 'metric', 'placement']);
        });
    }

    public function down(): void
    {
        Schema::table('season_rewards', function (Blueprint $table) {
            $table->dropUnique(['season_id', 'metric', 'placement']);
            $table->unique(['season_id', 'placement']);
            $table->dropColumn('metric');
        });
    }
};
