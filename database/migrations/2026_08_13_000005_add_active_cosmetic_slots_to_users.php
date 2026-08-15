<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('active_title_slug')->nullable()->after('free_chest_claimed_at');
            $table->string('active_frame_slug')->nullable()->after('active_title_slug');
            $table->string('active_card_back_slug')->nullable()->after('active_frame_slug');
            $table->string('active_victory_fx_slug')->nullable()->after('active_card_back_slug');
            // Assembled heraldry: { shape, pattern, charge, tincture1, tincture2 }.
            $table->json('crest_config')->nullable()->after('active_victory_fx_slug');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'active_title_slug',
                'active_frame_slug',
                'active_card_back_slug',
                'active_victory_fx_slug',
                'crest_config',
            ]);
        });
    }
};
