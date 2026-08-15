<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cosmetic;
use Illuminate\Database\Seeder;

class CosmeticSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalogue() as $entry) {
            Cosmetic::updateOrCreate(
                ['slug' => $entry['slug']],
                [
                    'type' => $entry['type'],
                    'name' => $entry['name'],
                    'rarity' => $entry['rarity'],
                    'value' => $entry['value'],
                    'is_default' => $entry['is_default'] ?? false,
                    'is_available' => true,
                    'sort' => $entry['sort'],
                ],
            );
        }
    }

    /**
     * The starter cosmetic catalogue. Everything here is purely visual — no item
     * affects gameplay, so the free Season Pass stays fair.
     *
     * @return list<array{type: string, slug: string, name: string, rarity: string, value: string, sort: int, is_default?: bool}>
     */
    private function catalogue(): array
    {
        return [
            // Titles — shown beside the player's name.
            ['type' => 'title', 'slug' => 'the-newbie', 'name' => 'The Newbie', 'rarity' => 'common', 'value' => 'The Newbie', 'sort' => 0, 'is_default' => true],
            ['type' => 'title', 'slug' => 'the-wise', 'name' => 'The Wise', 'rarity' => 'rare', 'value' => 'The Wise', 'sort' => 1],
            ['type' => 'title', 'slug' => 'kingmaker', 'name' => 'Kingmaker', 'rarity' => 'epic', 'value' => 'Kingmaker', 'sort' => 2],
            ['type' => 'title', 'slug' => 'dragonsbane', 'name' => 'Dragonsbane', 'rarity' => 'epic', 'value' => 'Dragonsbane', 'sort' => 3],
            ['type' => 'title', 'slug' => 'keeper-of-the-vault', 'name' => 'Keeper of the Vault', 'rarity' => 'legendary', 'value' => 'Keeper of the Vault', 'sort' => 4],

            // Avatar frames — a ring style keyed by `value`.
            ['type' => 'frame', 'slug' => 'iron-ring', 'name' => 'Iron Ring', 'rarity' => 'common', 'value' => 'iron', 'sort' => 0, 'is_default' => true],
            ['type' => 'frame', 'slug' => 'bronze-ring', 'name' => 'Bronze Ring', 'rarity' => 'common', 'value' => 'bronze', 'sort' => 1],
            ['type' => 'frame', 'slug' => 'silver-ring', 'name' => 'Silver Ring', 'rarity' => 'rare', 'value' => 'silver', 'sort' => 2],
            ['type' => 'frame', 'slug' => 'gold-ring', 'name' => 'Gold Ring', 'rarity' => 'epic', 'value' => 'gold', 'sort' => 3],
            ['type' => 'frame', 'slug' => 'royal-ring', 'name' => 'Royal Ring', 'rarity' => 'legendary', 'value' => 'royal', 'sort' => 4],

            // Card backs — keyed by `value`.
            ['type' => 'card_back', 'slug' => 'parchment', 'name' => 'Parchment', 'rarity' => 'common', 'value' => 'parchment', 'sort' => 0, 'is_default' => true],
            ['type' => 'card_back', 'slug' => 'midnight', 'name' => 'Midnight', 'rarity' => 'rare', 'value' => 'midnight', 'sort' => 1],
            ['type' => 'card_back', 'slug' => 'royal-crest-back', 'name' => 'Royal Crest', 'rarity' => 'epic', 'value' => 'royal_crest', 'sort' => 2],

            // Victory effects — keyed by `value` (matches VictoryEffect render keys).
            ['type' => 'victory_fx', 'slug' => 'gold-rain', 'name' => 'Golden Rain', 'rarity' => 'common', 'value' => 'gold_rain', 'sort' => 0, 'is_default' => true],
            ['type' => 'victory_fx', 'slug' => 'petals', 'name' => 'Falling Petals', 'rarity' => 'rare', 'value' => 'petals', 'sort' => 1],
            ['type' => 'victory_fx', 'slug' => 'embers', 'name' => 'Rising Embers', 'rarity' => 'epic', 'value' => 'embers', 'sort' => 2],
            ['type' => 'victory_fx', 'slug' => 'confetti', 'name' => 'Confetti Burst', 'rarity' => 'rare', 'value' => 'confetti', 'sort' => 3],
            ['type' => 'victory_fx', 'slug' => 'fireworks', 'name' => 'Fireworks', 'rarity' => 'epic', 'value' => 'fireworks', 'sort' => 4],
            ['type' => 'victory_fx', 'slug' => 'starfall', 'name' => 'Starfall', 'rarity' => 'epic', 'value' => 'starfall', 'sort' => 5],
            ['type' => 'victory_fx', 'slug' => 'snow', 'name' => 'Winter Snow', 'rarity' => 'legendary', 'value' => 'snow', 'sort' => 6],

            // Crest styles — the approved heraldic silhouettes (value = render id 1-15).
            // The first three are unlocked by default; the rest are earned.
            ['type' => 'crest_style', 'slug' => 'style-sovereign', 'name' => 'Sovereign', 'rarity' => 'common', 'value' => '1', 'sort' => 0, 'is_default' => true],
            ['type' => 'crest_style', 'slug' => 'style-quartered', 'name' => 'Quartered', 'rarity' => 'common', 'value' => '2', 'sort' => 1, 'is_default' => true],
            ['type' => 'crest_style', 'slug' => 'style-chevron', 'name' => 'Chevron', 'rarity' => 'common', 'value' => '3', 'sort' => 2, 'is_default' => true],
            ['type' => 'crest_style', 'slug' => 'style-council', 'name' => 'Council', 'rarity' => 'rare', 'value' => '4', 'sort' => 3],
            ['type' => 'crest_style', 'slug' => 'style-pennon', 'name' => 'Pennon', 'rarity' => 'epic', 'value' => '5', 'sort' => 4],
            ['type' => 'crest_style', 'slug' => 'style-swords', 'name' => 'Crossed Swords', 'rarity' => 'rare', 'value' => '6', 'sort' => 5],
            ['type' => 'crest_style', 'slug' => 'style-crown', 'name' => 'Crown', 'rarity' => 'epic', 'value' => '7', 'sort' => 6],
            ['type' => 'crest_style', 'slug' => 'style-tower', 'name' => 'Tower', 'rarity' => 'rare', 'value' => '8', 'sort' => 7],
            ['type' => 'crest_style', 'slug' => 'style-fleur', 'name' => 'Fleur-de-lis', 'rarity' => 'rare', 'value' => '9', 'sort' => 8],
            ['type' => 'crest_style', 'slug' => 'style-saltire', 'name' => 'Saltire', 'rarity' => 'rare', 'value' => '10', 'sort' => 9],
            ['type' => 'crest_style', 'slug' => 'style-sun', 'name' => 'Sunburst', 'rarity' => 'epic', 'value' => '11', 'sort' => 10],
            ['type' => 'crest_style', 'slug' => 'style-anchor', 'name' => 'Anchor', 'rarity' => 'rare', 'value' => '12', 'sort' => 11],
            ['type' => 'crest_style', 'slug' => 'style-lion', 'name' => 'Lion', 'rarity' => 'legendary', 'value' => '13', 'sort' => 12],
            ['type' => 'crest_style', 'slug' => 'style-dragon', 'name' => 'Dragon', 'rarity' => 'legendary', 'value' => '14', 'sort' => 13],
            ['type' => 'crest_style', 'slug' => 'style-eagle', 'name' => 'Eagle', 'rarity' => 'legendary', 'value' => '15', 'sort' => 14],

            // Crest colours — tier palettes (value = palette id 0-4). Wooden is the
            // default; the rest unlock as progression rewards.
            ['type' => 'crest_colour', 'slug' => 'colour-wooden', 'name' => 'Wooden', 'rarity' => 'common', 'value' => '0', 'sort' => 0, 'is_default' => true],
            ['type' => 'crest_colour', 'slug' => 'colour-bronze', 'name' => 'Bronze', 'rarity' => 'common', 'value' => '1', 'sort' => 1],
            ['type' => 'crest_colour', 'slug' => 'colour-silver', 'name' => 'Silver', 'rarity' => 'rare', 'value' => '2', 'sort' => 2],
            ['type' => 'crest_colour', 'slug' => 'colour-gold', 'name' => 'Gold', 'rarity' => 'epic', 'value' => '3', 'sort' => 3],
            ['type' => 'crest_colour', 'slug' => 'colour-obsidian', 'name' => 'Obsidian', 'rarity' => 'legendary', 'value' => '4', 'sort' => 4],
        ];
    }
}
