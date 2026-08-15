<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Put every existing player on the seeded default "The Newbie" title so
        // their name carries a vanity title, matching the new-user default.
        DB::table('users')
            ->whereNull('active_title_slug')
            ->update(['active_title_slug' => 'the-newbie']);
    }

    // Irreversible: once backfilled, a genuinely-null title can't be told apart
    // from a backfilled one, so there is no safe down migration.
};
