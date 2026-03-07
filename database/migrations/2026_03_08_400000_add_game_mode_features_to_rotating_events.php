<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rotating_events', function (Blueprint $table) {
            $table->json('card_pool')->nullable()->after('modifiers');
            $table->json('item_pool')->nullable()->after('card_pool');
            $table->json('event_pool')->nullable()->after('item_pool');
            $table->json('character_pool')->nullable()->after('event_pool');
            $table->foreignId('fixed_event_id')->nullable()->constrained('events')->nullOnDelete()->after('character_pool');
            $table->integer('total_rounds')->nullable()->after('fixed_event_id');
            $table->json('xp_config')->nullable()->after('total_rounds');
            $table->boolean('affects_elo')->default(false)->after('xp_config');
            $table->string('theme_color', 7)->nullable()->after('affects_elo');
        });
    }

    public function down(): void
    {
        Schema::table('rotating_events', function (Blueprint $table) {
            $table->dropForeign(['fixed_event_id']);
            $table->dropColumn([
                'card_pool',
                'item_pool',
                'event_pool',
                'character_pool',
                'fixed_event_id',
                'total_rounds',
                'xp_config',
                'affects_elo',
                'theme_color',
            ]);
        });
    }
};
