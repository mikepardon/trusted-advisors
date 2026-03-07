<?php

namespace Database\Seeders;

use App\Models\Curse;
use Illuminate\Database\Seeder;

class CurseSeeder extends Seeder
{
    public function run(): void
    {
        $curses = [
            // ===========================
            // LOSE DIE CURSES
            // ===========================
            [
                'name' => 'The Withering',
                'description' => 'Your strength fades like autumn leaves. Each task grows harder as exhaustion takes hold.',
                'negative_effect' => ['type' => 'lose_die', 'value' => 1],
                'positive_effect' => ['type' => 'xp_multiplier', 'value' => 1.5],
                'negative_effect_duel' => ['type' => 'lose_die', 'value' => 1],
                'positive_effect_duel' => ['type' => 'opponent_difficulty', 'value' => 1],
            ],
            [
                'name' => 'Shackles of Sloth',
                'description' => 'Invisible chains weigh down your limbs. You move slower, but your resolve deepens.',
                'negative_effect' => ['type' => 'lose_die', 'value' => 1],
                'positive_effect' => ['type' => 'score_bonus', 'value' => 8],
                'negative_effect_duel' => ['type' => 'lose_die', 'value' => 1],
                'positive_effect_duel' => ['type' => 'score_bonus', 'value' => 5],
            ],

            // ===========================
            // STAT PER ROUND CURSES
            // ===========================
            [
                'name' => 'Plague of Famine',
                'description' => 'Crops wilt and stores rot wherever you tread. The kingdom starves slowly.',
                'negative_effect' => ['type' => 'stat_per_round', 'stat' => 'food', 'value' => -1],
                'positive_effect' => ['type' => 'auto_max_stat', 'count' => 1],
                'negative_effect_duel' => ['type' => 'stat_per_round', 'stat' => 'food', 'value' => -1],
                'positive_effect_duel' => ['type' => 'opponent_lose_die', 'rounds' => 2],
            ],
            [
                'name' => 'Shadow of Doubt',
                'description' => 'Whispers spread through the court, eroding trust in your advisors.',
                'negative_effect' => ['type' => 'stat_per_round', 'stat' => 'influence', 'value' => -1],
                'positive_effect' => ['type' => 'xp_multiplier', 'value' => 1.5],
                'negative_effect_duel' => ['type' => 'stat_per_round', 'stat' => 'influence', 'value' => -1],
                'positive_effect_duel' => ['type' => 'stat_per_round', 'stat' => 'security', 'value' => 1],
            ],
            [
                'name' => 'The Bleeding Coffers',
                'description' => 'Gold vanishes from the treasury like water through cracked stone.',
                'negative_effect' => ['type' => 'stat_per_round', 'stat' => 'wealth', 'value' => -1],
                'positive_effect' => ['type' => 'stat_per_round', 'stat' => 'happiness', 'value' => 1],
                'negative_effect_duel' => ['type' => 'stat_per_round', 'stat' => 'wealth', 'value' => -1],
                'positive_effect_duel' => ['type' => 'stat_per_round', 'stat' => 'happiness', 'value' => 1],
            ],
            [
                'name' => 'Heretic\'s Brand',
                'description' => 'The clergy shuns you. Temples close their doors as faith in the crown crumbles.',
                'negative_effect' => ['type' => 'stat_per_round', 'stat' => 'religion', 'value' => -1],
                'positive_effect' => ['type' => 'score_bonus', 'value' => 6],
                'negative_effect_duel' => ['type' => 'stat_per_round', 'stat' => 'religion', 'value' => -1],
                'positive_effect_duel' => ['type' => 'score_bonus', 'value' => 4],
            ],
            [
                'name' => 'Bandit\'s Bane',
                'description' => 'The roads grow dangerous. Bandits multiply and patrols cannot keep pace.',
                'negative_effect' => ['type' => 'stat_per_round', 'stat' => 'security', 'value' => -1],
                'positive_effect' => ['type' => 'xp_multiplier', 'value' => 1.3],
                'negative_effect_duel' => ['type' => 'stat_per_round', 'stat' => 'security', 'value' => -1],
                'positive_effect_duel' => ['type' => 'opponent_difficulty', 'value' => 1],
            ],
            [
                'name' => 'The Sorrow Tide',
                'description' => 'A melancholy spreads through the land. Festivals fall silent and laughter fades.',
                'negative_effect' => ['type' => 'stat_per_round', 'stat' => 'happiness', 'value' => -1],
                'positive_effect' => ['type' => 'auto_max_stat', 'count' => 1],
                'negative_effect_duel' => ['type' => 'stat_per_round', 'stat' => 'happiness', 'value' => -1],
                'positive_effect_duel' => ['type' => 'auto_max_stat', 'count' => 1],
            ],

            // ===========================
            // DIFFICULTY MODIFIER CURSES
            // ===========================
            [
                'name' => 'Fog of War',
                'description' => 'A thick mist obscures every path. All decisions become harder to navigate.',
                'negative_effect' => ['type' => 'difficulty_modifier', 'value' => 1],
                'positive_effect' => ['type' => 'xp_multiplier', 'value' => 1.5],
                'negative_effect_duel' => ['type' => 'difficulty_modifier', 'value' => 1],
                'positive_effect_duel' => ['type' => 'xp_multiplier', 'value' => 1.5],
            ],
            [
                'name' => 'The Labyrinth',
                'description' => 'Every corridor twists, every plan tangles. Nothing comes easy under this hex.',
                'negative_effect' => ['type' => 'difficulty_modifier', 'value' => 2],
                'positive_effect' => ['type' => 'score_bonus', 'value' => 10],
                'negative_effect_duel' => ['type' => 'difficulty_modifier', 'value' => 2],
                'positive_effect_duel' => ['type' => 'score_bonus', 'value' => 8],
            ],
            [
                'name' => 'Serpent\'s Riddle',
                'description' => 'A cunning serpent poses impossible puzzles. Tasks that were simple now confound.',
                'negative_effect' => ['type' => 'difficulty_modifier', 'value' => 1],
                'positive_effect' => ['type' => 'auto_max_stat', 'count' => 1],
                'negative_effect_duel' => ['type' => 'difficulty_modifier', 'value' => 1],
                'positive_effect_duel' => ['type' => 'opponent_lose_die', 'rounds' => 3],
            ],

            // ===========================
            // DOUBLE NEGATIVE CURSES
            // ===========================
            [
                'name' => 'Curse of Amplification',
                'description' => 'Every failure echoes twice. Setbacks strike with doubled fury.',
                'negative_effect' => ['type' => 'double_negative'],
                'positive_effect' => ['type' => 'xp_multiplier', 'value' => 2.0],
                'negative_effect_duel' => ['type' => 'double_negative'],
                'positive_effect_duel' => ['type' => 'xp_multiplier', 'value' => 2.0],
            ],
            [
                'name' => 'The Mirror\'s Spite',
                'description' => 'A cursed mirror reflects your failures back twofold. But the lessons learned are invaluable.',
                'negative_effect' => ['type' => 'double_negative'],
                'positive_effect' => ['type' => 'score_bonus', 'value' => 12],
                'negative_effect_duel' => ['type' => 'double_negative'],
                'positive_effect_duel' => ['type' => 'score_bonus', 'value' => 10],
            ],
            [
                'name' => 'Echo of Ruin',
                'description' => 'Ancient magic reverberates through your realm. Every wound cuts deeper, but triumph awaits the enduring.',
                'negative_effect' => ['type' => 'double_negative'],
                'positive_effect' => ['type' => 'auto_max_stat', 'count' => 2],
                'negative_effect_duel' => ['type' => 'double_negative'],
                'positive_effect_duel' => ['type' => 'auto_max_stat', 'count' => 2],
            ],
        ];

        foreach ($curses as $curse) {
            Curse::updateOrCreate(
                ['name' => $curse['name']],
                $curse
            );
        }
    }
}
