<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        Event::truncate();

        $events = [
            // Rounds 1-7: Standard stat modifier
            [
                'title' => 'Plague Spreads Across the Shires',
                'effect' => 'A devastating plague ravages the outer shires, causing widespread suffering. Each failed decision deepens the crisis. Happiness suffers with every setback.',
                'stat_modifiers' => ['happiness' => -1],
                'mechanic' => 'stat_modifier',
                'mechanic_data' => null,
            ],
            // Rounds 8-14: Grant items to all players
            [
                'title' => 'Bountiful Harvest',
                'effect' => 'The harvest exceeds all expectations. Surplus goods flow into the castle, and each advisor receives a gift from the grateful shires.',
                'stat_modifiers' => null,
                'mechanic' => 'grant_items',
                'mechanic_data' => ['random' => true],
            ],
            // Rounds 15-21: Reduced dice (harsh conditions)
            [
                'title' => 'Bitter Winter',
                'effect' => 'A harsh winter grips the kingdom, slowing all operations. Advisors struggle through the cold, working with diminished capacity.',
                'stat_modifiers' => null,
                'mechanic' => 'reduce_dice',
                'mechanic_data' => ['amount' => 1],
            ],
            // Rounds 22-24: Altered deal (3 cards, pick 1 positive)
            [
                'title' => 'The Church Demands Tribute',
                'effect' => 'The clergy declare that God is displeased and demand greater tithes. The council faces harder choices with fewer favorable options.',
                'stat_modifiers' => null,
                'mechanic' => 'altered_deal',
                'mechanic_data' => ['positive_cards' => 1, 'negative_cards' => 2],
            ],
            // Rounds 29+: Extended game events
            [
                'title' => 'War with the Scots',
                'effect' => 'Scottish forces mass along the northern border, threatening invasion. Military strength is tested at every turn. All challenges requiring strength become harder.',
                'stat_modifiers' => ['security' => -1],
                'mechanic' => 'stat_modifier',
                'mechanic_data' => null,
            ],
            [
                'title' => 'Golden Age of Trade',
                'effect' => 'Trade routes flourish and merchants bring wealth from distant lands. Successful decisions yield greater rewards. Each success brings bonus wealth to the kingdom.',
                'stat_modifiers' => ['wealth' => 2],
                'mechanic' => 'stat_modifier',
                'mechanic_data' => null,
            ],
            [
                'title' => 'The King Opens the Armory',
                'effect' => 'The King opens the royal armory to his trusted advisors. Each council member receives equipment to aid their duties.',
                'stat_modifiers' => null,
                'mechanic' => 'grant_items',
                'mechanic_data' => ['random' => true],
            ],
            [
                'title' => 'Lords in Dispute',
                'effect' => 'Political infighting paralyzes the Lord\'s Council. Influence wanes as factions squabble, and the council must navigate with fewer favorable options.',
                'stat_modifiers' => ['influence' => -1],
                'mechanic' => 'altered_deal',
                'mechanic_data' => ['positive_cards' => 1, 'negative_cards' => 2],
            ],
        ];

        foreach ($events as $event) {
            Event::create($event);
        }
    }
}
