<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Equip the free default frame / card back / victory effect for every
        // existing player who hasn't chosen one, matching the new-user default.
        DB::transaction(function (): void {
            DB::table('users')
                ->whereNull('active_frame_slug')
                ->update(['active_frame_slug' => 'iron-ring']);
            DB::table('users')
                ->whereNull('active_card_back_slug')
                ->update(['active_card_back_slug' => 'parchment']);
            DB::table('users')
                ->whereNull('active_victory_fx_slug')
                ->update(['active_victory_fx_slug' => 'gold-rain']);
        });
    }

    // Irreversible: a chosen-vs-backfilled default can't be told apart afterwards.
};
