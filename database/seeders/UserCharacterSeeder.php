<?php

namespace Database\Seeders;

use App\Models\GamePlayer;
use App\Models\GameRule;
use App\Models\Unlockable;
use App\Models\User;
use App\Models\UserCharacter;
use App\Models\UserUnlockable;
use Illuminate\Database\Seeder;

class UserCharacterSeeder extends Seeder
{
    public function run(): void
    {
        $starterIds = GameRule::getValue('starter_character_ids', [1, 2]);

        // For each existing user (non-bot), create UserCharacter records
        User::where('is_bot', false)->chunk(100, function ($users) use ($starterIds) {
            foreach ($users as $user) {
                // Assign starter characters
                foreach ($starterIds as $charId) {
                    UserCharacter::firstOrCreate([
                        'user_id' => $user->id,
                        'character_id' => $charId,
                    ]);
                }

                // Assign characters unlocked via UserUnlockable
                $unlockedCharUnlockables = UserUnlockable::where('user_id', $user->id)
                    ->whereHas('unlockable', fn ($q) => $q->where('type', 'character'))
                    ->with('unlockable')
                    ->get();

                foreach ($unlockedCharUnlockables as $uu) {
                    if ($uu->unlockable && $uu->unlockable->entity_id) {
                        UserCharacter::firstOrCreate([
                            'user_id' => $user->id,
                            'character_id' => $uu->unlockable->entity_id,
                        ]);
                    }
                }

                // Backfill XP from historical games (~50 XP per completed game per character)
                $charGameCounts = GamePlayer::where('user_id', $user->id)
                    ->whereNotNull('character_id')
                    ->whereHas('game', fn ($q) => $q->where('status', 'completed'))
                    ->selectRaw('character_id, count(*) as game_count')
                    ->groupBy('character_id')
                    ->get();

                foreach ($charGameCounts as $cg) {
                    $uc = UserCharacter::where('user_id', $user->id)
                        ->where('character_id', $cg->character_id)
                        ->first();

                    if ($uc) {
                        $backfillXp = $cg->game_count * 50;
                        $uc->xp = $backfillXp;
                        $uc->level = UserCharacter::calculateLevel($backfillXp);
                        $uc->save();
                    }
                }
            }
        });
    }
}
