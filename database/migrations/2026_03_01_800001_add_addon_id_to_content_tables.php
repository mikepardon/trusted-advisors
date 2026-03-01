<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['characters', 'events', 'items', 'daily_challenges'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('addon_id')->nullable()->constrained('addons')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $tables = ['characters', 'events', 'items', 'daily_challenges'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('addon_id');
            });
        }
    }
};
