<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            // === wins tier_group ===
            [
                'key' => 'first_victory',
                'name' => 'First Victory',
                'description' => 'Win your first game.',
                'icon' => 'trophy',
                'category' => 'milestone',
                'criteria' => ['type' => 'total_wins', 'count' => 1],
                'tier' => 1,
                'tier_group' => 'wins',
                'reward_xp' => 25,
            ],
            [
                'key' => 'warrior',
                'name' => 'Warrior',
                'description' => 'Win 5 games.',
                'icon' => 'shield',
                'category' => 'milestone',
                'criteria' => ['type' => 'total_wins', 'count' => 5],
                'tier' => 2,
                'tier_group' => 'wins',
                'reward_xp' => 50,
            ],
            [
                'key' => 'veteran',
                'name' => 'Veteran',
                'description' => 'Win 10 games.',
                'icon' => 'shield',
                'category' => 'milestone',
                'criteria' => ['type' => 'total_wins', 'count' => 10],
                'tier' => 3,
                'tier_group' => 'wins',
                'reward_xp' => 75,
            ],
            [
                'key' => 'champion',
                'name' => 'Champion',
                'description' => 'Win 50 games.',
                'icon' => 'crown',
                'category' => 'milestone',
                'criteria' => ['type' => 'total_wins', 'count' => 50],
                'tier' => 4,
                'tier_group' => 'wins',
                'reward_xp' => 200,
            ],

            // === streak tier_group ===
            [
                'key' => 'hot_streak_3',
                'name' => 'Hot Streak',
                'description' => 'Win 3 games in a row.',
                'icon' => 'flame',
                'category' => 'streak',
                'criteria' => ['type' => 'win_streak', 'count' => 3],
                'tier' => 1,
                'tier_group' => 'streak',
                'reward_xp' => 50,
            ],
            [
                'key' => 'unstoppable_5',
                'name' => 'Unstoppable',
                'description' => 'Win 5 games in a row.',
                'icon' => 'lightning',
                'category' => 'streak',
                'criteria' => ['type' => 'win_streak', 'count' => 5],
                'tier' => 2,
                'tier_group' => 'streak',
                'reward_xp' => 100,
            ],
            [
                'key' => 'legendary_streak',
                'name' => 'Legendary',
                'description' => 'Win 10 games in a row.',
                'icon' => 'lightning',
                'category' => 'streak',
                'criteria' => ['type' => 'win_streak', 'count' => 10],
                'tier' => 3,
                'tier_group' => 'streak',
                'reward_xp' => 250,
            ],

            // === duel tier_group ===
            [
                'key' => 'duelist',
                'name' => 'Duelist',
                'description' => 'Win 5 duel games.',
                'icon' => 'swords',
                'category' => 'duel',
                'criteria' => ['type' => 'duel_wins', 'count' => 5],
                'tier' => 1,
                'tier_group' => 'duel',
                'reward_xp' => 50,
            ],
            [
                'key' => 'duel_master',
                'name' => 'Duel Master',
                'description' => 'Win 25 duel games.',
                'icon' => 'crossed_swords',
                'category' => 'duel',
                'criteria' => ['type' => 'duel_wins', 'count' => 25],
                'tier' => 2,
                'tier_group' => 'duel',
                'reward_xp' => 150,
            ],

            // === level tier_group ===
            [
                'key' => 'level_5',
                'name' => 'Apprentice',
                'description' => 'Reach level 5.',
                'icon' => 'book',
                'category' => 'progression',
                'criteria' => ['type' => 'level_reached', 'value' => 5],
                'tier' => 1,
                'tier_group' => 'level',
                'reward_xp' => 25,
            ],
            [
                'key' => 'level_10',
                'name' => 'Adept',
                'description' => 'Reach level 10.',
                'icon' => 'wizard',
                'category' => 'progression',
                'criteria' => ['type' => 'level_reached', 'value' => 10],
                'tier' => 2,
                'tier_group' => 'level',
                'reward_xp' => 50,
            ],
            [
                'key' => 'level_20',
                'name' => 'Master Advisor',
                'description' => 'Reach level 20.',
                'icon' => 'castle',
                'category' => 'progression',
                'criteria' => ['type' => 'level_reached', 'value' => 20],
                'tier' => 3,
                'tier_group' => 'level',
                'reward_xp' => 150,
            ],

            // === elo tier_group ===
            [
                'key' => 'elo_1200',
                'name' => 'Rising Star',
                'description' => 'Reach an ELO rating of 1200.',
                'icon' => 'arrow_up',
                'category' => 'elo',
                'criteria' => ['type' => 'elo_reached', 'value' => 1200],
                'tier' => 1,
                'tier_group' => 'elo',
                'reward_xp' => 50,
            ],
            [
                'key' => 'elo_1500',
                'name' => 'Grand Master',
                'description' => 'Reach an ELO rating of 1500.',
                'icon' => 'diamond',
                'category' => 'elo',
                'criteria' => ['type' => 'elo_reached', 'value' => 1500],
                'tier' => 2,
                'tier_group' => 'elo',
                'reward_xp' => 150,
            ],

            // === Standalone (no tier_group) ===
            [
                'key' => 'seasoned_player',
                'name' => 'Seasoned Player',
                'description' => 'Play 50 games.',
                'icon' => 'scroll',
                'category' => 'milestone',
                'criteria' => ['type' => 'games_played', 'count' => 50],
                'tier' => 1,
                'tier_group' => null,
                'reward_xp' => 75,
            ],
            [
                'key' => 'perfect_kingdom',
                'name' => 'Perfect Kingdom',
                'description' => 'Finish a game with 3 stats at maximum.',
                'icon' => 'star',
                'category' => 'excellence',
                'criteria' => ['type' => 'perfect_stats', 'count' => 3],
                'tier' => 1,
                'tier_group' => null,
                'reward_xp' => 100,
            ],
            [
                'key' => 'diverse_counsel',
                'name' => 'Diverse Counsel',
                'description' => 'Play with 5 different characters.',
                'icon' => 'people',
                'category' => 'exploration',
                'criteria' => ['type' => 'unique_characters', 'count' => 5],
                'tier' => 1,
                'tier_group' => null,
                'reward_xp' => 50,
            ],
        ];

        foreach ($achievements as $data) {
            Achievement::updateOrCreate(
                ['key' => $data['key']],
                $data
            );
        }
    }
}
