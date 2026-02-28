<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // === POSITIVE ITEMS (roll_bonus) ===
            [
                'name' => 'Royal Signet Ring',
                'description' => 'A gold ring bearing the King\'s seal, granting its holder authority in all matters of the realm.',
                'effect' => ['bonus_type' => 'roll_bonus', 'bonus_value' => 1],
                'effect_type' => 'passive',
                'is_negative' => false,
                'is_consumable' => false,
            ],
            [
                'name' => 'Blessed Rosary',
                'description' => 'Prayer beads consecrated by the Archbishop of Canterbury. They bring clarity and calm.',
                'effect' => ['bonus_type' => 'roll_bonus', 'bonus_value' => 1],
                'effect_type' => 'passive',
                'is_negative' => false,
                'is_consumable' => false,
            ],
            [
                'name' => 'Merchant\'s Ledger',
                'description' => 'A detailed accounting book containing trade secrets and debts owed by half the guild masters.',
                'effect' => ['bonus_type' => 'roll_bonus', 'bonus_value' => 1],
                'effect_type' => 'passive',
                'is_negative' => false,
                'is_consumable' => false,
            ],
            [
                'name' => 'Longbow of Sherwood',
                'description' => 'A masterwork yew longbow said to have belonged to an outlaw of great renown.',
                'effect' => ['bonus_type' => 'roll_bonus', 'bonus_value' => 1],
                'effect_type' => 'passive',
                'is_negative' => false,
                'is_consumable' => false,
            ],
            [
                'name' => 'Knight\'s Oath',
                'description' => 'A sealed parchment binding a knight-errant to your service. His sword is yours to command.',
                'effect' => ['bonus_type' => 'roll_bonus', 'bonus_value' => 1],
                'effect_type' => 'passive',
                'is_negative' => false,
                'is_consumable' => false,
            ],
            [
                'name' => 'Crown of Laurels',
                'description' => 'A golden wreath awarded for great victories, commanding respect from all who behold it.',
                'effect' => ['bonus_type' => 'roll_bonus', 'bonus_value' => 2],
                'effect_type' => 'passive',
                'is_negative' => false,
                'is_consumable' => false,
            ],
            [
                'name' => 'Excalibur\'s Shard',
                'description' => 'A sliver of the legendary blade, still humming with ancient power.',
                'effect' => ['bonus_type' => 'roll_bonus', 'bonus_value' => 2],
                'effect_type' => 'passive',
                'is_negative' => false,
                'is_consumable' => false,
            ],
            [
                'name' => 'Spy Network Map',
                'description' => 'A coded map marking informant locations throughout the kingdom. Knowledge is power.',
                'effect' => ['bonus_type' => 'roll_bonus', 'bonus_value' => 2],
                'effect_type' => 'passive',
                'is_negative' => false,
                'is_consumable' => false,
            ],
            [
                'name' => 'Holy Grail Fragment',
                'description' => 'A shard from a sacred chalice, radiating divine warmth and wisdom.',
                'effect' => ['bonus_type' => 'roll_bonus', 'bonus_value' => 2],
                'effect_type' => 'passive',
                'is_negative' => false,
                'is_consumable' => false,
            ],

            // === POSITIVE ITEMS (difficulty_reduction) ===
            [
                'name' => 'Cartographer\'s Charts',
                'description' => 'Detailed maps of the realm that make even the most daunting tasks seem manageable.',
                'effect' => ['bonus_type' => 'difficulty_reduction', 'bonus_value' => 1],
                'effect_type' => 'passive',
                'is_negative' => false,
                'is_consumable' => false,
            ],
            [
                'name' => 'Alchemist\'s Tonic',
                'description' => 'A shimmering elixir that sharpens the mind and steadies the hand.',
                'effect' => ['bonus_type' => 'difficulty_reduction', 'bonus_value' => 1],
                'effect_type' => 'passive',
                'is_negative' => false,
                'is_consumable' => false,
            ],
            [
                'name' => 'Papal Bull',
                'description' => 'A decree from Rome itself. Doors open and obstacles melt before its authority.',
                'effect' => ['bonus_type' => 'difficulty_reduction', 'bonus_value' => 2],
                'effect_type' => 'passive',
                'is_negative' => false,
                'is_consumable' => false,
            ],

            // === NEGATIVE / CURSED ITEMS ===
            [
                'name' => 'Cursed Amulet',
                'description' => 'A tarnished amulet that whispers dark thoughts. Its presence weakens resolve.',
                'effect' => ['bonus_type' => 'roll_penalty', 'bonus_value' => -1],
                'effect_type' => 'passive',
                'is_negative' => true,
                'is_consumable' => false,
            ],
            [
                'name' => 'Plague Rat\'s Tooth',
                'description' => 'A foul relic that brings misfortune to all who carry it. The stench alone drives allies away.',
                'effect' => ['bonus_type' => 'roll_penalty', 'bonus_value' => -1],
                'effect_type' => 'passive',
                'is_negative' => true,
                'is_consumable' => false,
            ],
            [
                'name' => 'Witch\'s Mark',
                'description' => 'A brand that appeared overnight. Townsfolk avert their eyes, and allies keep their distance.',
                'effect' => ['bonus_type' => 'roll_penalty', 'bonus_value' => -1],
                'effect_type' => 'passive',
                'is_negative' => true,
                'is_consumable' => false,
            ],
            [
                'name' => 'Blood Debt Scroll',
                'description' => 'An unpayable debt to a shadowy moneylender. Its weight grows heavier with each passing month.',
                'effect' => ['bonus_type' => 'roll_penalty', 'bonus_value' => -2],
                'effect_type' => 'passive',
                'is_negative' => true,
                'is_consumable' => false,
            ],
            [
                'name' => 'Traitor\'s Brand',
                'description' => 'A mark of betrayal burned into the flesh. No one trusts the one who bears it.',
                'effect' => ['bonus_type' => 'roll_penalty', 'bonus_value' => -2],
                'effect_type' => 'passive',
                'is_negative' => true,
                'is_consumable' => false,
            ],
            [
                'name' => 'Shattered Mirror',
                'description' => 'Seven years of misfortune contained in broken glass. Every plan goes slightly wrong.',
                'effect' => ['bonus_type' => 'difficulty_increase', 'bonus_value' => 1],
                'effect_type' => 'passive',
                'is_negative' => true,
                'is_consumable' => false,
            ],
            [
                'name' => 'Demon\'s Contract',
                'description' => 'A pact signed in blood. The terms are unclear, but the consequences are not.',
                'effect' => ['bonus_type' => 'difficulty_increase', 'bonus_value' => 2],
                'effect_type' => 'passive',
                'is_negative' => true,
                'is_consumable' => false,
            ],

            // === CONSUMABLE / IMMEDIATE ITEMS ===
            [
                'name' => 'Healing Draught',
                'description' => 'A warm herbal brew that restores vitality to the realm\'s food stores.',
                'effect' => ['bonus_type' => 'stat_boost', 'bonus_value' => 2, 'stat' => 'food'],
                'effect_type' => 'immediate',
                'is_negative' => false,
                'is_consumable' => true,
            ],
            [
                'name' => 'War Horn',
                'description' => 'A mighty horn that rallies weary soldiers, restoring a lost die to an advisor.',
                'effect' => ['bonus_type' => 'heal_die', 'bonus_value' => 1],
                'effect_type' => 'immediate',
                'is_negative' => false,
                'is_consumable' => true,
            ],
            [
                'name' => 'Merchant\'s Windfall',
                'description' => 'A caravan of unexpected riches arrives at the gates, bolstering the treasury.',
                'effect' => ['bonus_type' => 'stat_boost', 'bonus_value' => 2, 'stat' => 'wealth'],
                'effect_type' => 'immediate',
                'is_negative' => false,
                'is_consumable' => true,
            ],
        ];

        foreach ($items as $item) {
            Item::updateOrCreate(
                ['name' => $item['name']],
                $item
            );
        }
    }
}
