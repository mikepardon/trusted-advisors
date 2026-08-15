<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserCharacter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The account and advisor XP curves changed (flat quadratic → cubic / explicit
     * thresholds). Stored levels are only recomputed at game end, so backfill them now
     * from existing XP; otherwise profiles show a level the new curve no longer supports.
     *
     * Irreversible (the previous per-row levels are not retained), so no down().
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            User::query()->select(['id', 'xp', 'level'])->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    $recalculated = User::calculateLevel((int) $user->xp);

                    if ($recalculated !== (int) $user->level) {
                        $user->level = $recalculated;
                        $user->saveQuietly();
                    }
                }
            });

            UserCharacter::query()->select(['id', 'xp', 'level'])->chunkById(500, function ($characters): void {
                foreach ($characters as $character) {
                    $recalculated = UserCharacter::calculateLevel((int) $character->xp);

                    if ($recalculated !== (int) $character->level) {
                        $character->level = $recalculated;
                        $character->saveQuietly();
                    }
                }
            });
        });
    }
};
