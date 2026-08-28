<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('season_pass_tiers', function (Blueprint $table) {
            $table->foreignId('reward_item_id')->nullable()->after('reward_cosmetic_id')
                ->index()->constrained('items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('season_pass_tiers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reward_item_id');
        });
    }
};
