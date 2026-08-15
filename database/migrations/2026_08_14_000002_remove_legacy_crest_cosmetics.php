<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The old assembler crest parts (shape / pattern / charge) are superseded
        // by the crest_style + crest_colour cosmetics, so retire them along with
        // any ownership rows that referenced them.
        DB::transaction(function (): void {
            $ids = DB::table('cosmetics')
                ->whereIn('type', ['crest_shape', 'crest_pattern', 'crest_charge'])
                ->pluck('id');

            if ($ids->isEmpty()) {
                return;
            }

            DB::table('user_cosmetics')->whereIn('cosmetic_id', $ids)->delete();
            DB::table('cosmetics')->whereIn('id', $ids)->delete();
        });
    }

    // Irreversible: the retired crest cosmetics can't be reconstructed.
};
