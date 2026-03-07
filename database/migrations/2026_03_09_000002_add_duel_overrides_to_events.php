<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('stat_modifiers_duel')->nullable()->after('stat_modifiers');
            $table->string('mechanic_duel')->nullable()->after('mechanic');
            $table->json('mechanic_data_duel')->nullable()->after('mechanic_data');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['stat_modifiers_duel', 'mechanic_duel', 'mechanic_data_duel']);
        });
    }
};
