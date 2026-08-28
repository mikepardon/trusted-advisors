<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Everyone implicitly owns starter items without a user_items row.
            $table->boolean('is_starter')->default(false)->after('target');
            // How often the item may be used: passive (always-on), per_round, per_game.
            $table->string('cadence')->default('per_game')->after('is_starter');
            // Presentation grouping (armour, coin, weapon, relic, scroll, potion, ...).
            $table->string('type')->nullable()->after('cadence');
            // Key resolved by the frontend icon registry (AppIcon / useIcons).
            $table->string('icon_key')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['is_starter', 'cadence', 'type', 'icon_key']);
        });
    }
};
