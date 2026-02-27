<?php

namespace Database\Seeders;

use App\Models\Character;
use Illuminate\Database\Seeder;

class CharacterSeeder extends Seeder
{
    public function run(): void
    {
        Character::truncate();

        $characters = [
            [
                'name' => 'Lord William',
                'description' => 'A seasoned noble who has spent decades navigating the treacherous politics of the King\'s court. His silver tongue and keen understanding of human nature make him an invaluable diplomat, though his years behind a desk have left him lacking in martial prowess.',
                'dice' => [
                    [3, 3, 4, 2, 2, 'WILD'],
                    [3, 3, 4, 2, 2, 3],
                    [3, 3, 4, 2, 2, 3],
                ],
                'wild_value' => 3,
                'wild_ability' => 'inspire',
                'wild_ability_description' => '+1 to total roll per player in the game',
            ],
            [
                'name' => 'Dame Aurelia',
                'description' => 'One of the realm\'s fiercest knights, Aurelia rose through the ranks through sheer determination and battlefield brilliance. She commands absolute loyalty from her soldiers and strikes fear into the kingdom\'s enemies, though the subtleties of court intrigue sometimes elude her.',
                'dice' => [
                    [4, 4, 3, 1, 1, 'WILD'],
                    [4, 4, 3, 1, 1, 3],
                    [4, 4, 3, 1, 1, 3],
                ],
                'wild_value' => 2,
                'wild_ability' => 'rally',
                'wild_ability_description' => 'Reroll the lowest die for a better result',
            ],
            [
                'name' => 'Brother Aldric',
                'description' => 'Keeper of the sacred relics and voice of God, Brother Aldric wields enormous spiritual influence over the people of England. His prophetic visions and deep connection to the divine make him a powerful ally, though his devotion sometimes blinds him to worldly concerns.',
                'dice' => [
                    [2, 3, 3, 2, 3, 'WILD'],
                    [2, 3, 3, 2, 3, 3],
                    [2, 3, 3, 2, 3, 3],
                ],
                'wild_value' => 4,
                'wild_ability' => 'divine',
                'wild_ability_description' => 'WILD counts as double its value',
            ],
            [
                'name' => 'Oswald the Trader',
                'description' => 'The wealthiest merchant in all of England, Oswald has trade connections in every port from the North Sea to the Mediterranean. His cunning business sense and vast network of informants give him leverage that even lords envy, though his loyalty is always to the highest bidder.',
                'dice' => [
                    [5, 5, 3, 1, 1, 'WILD'],
                    [5, 5, 3, 1, 1, 3],
                    [5, 5, 3, 1, 1, 3],
                ],
                'wild_value' => 3,
                'wild_ability' => 'gamble',
                'wild_ability_description' => 'Reroll all dice for a potentially bigger payoff',
            ],
            [
                'name' => 'Meredith the Shadow',
                'description' => 'A shadow in the night, Meredith serves as the King\'s most effective intelligence operative. Her network of agents spans the kingdom, and her ability to uncover secrets is unmatched. She operates best in the darkness, where others fear to tread.',
                'dice' => [
                    [3, 3, 2, 2, 4, 'WILD'],
                    [3, 3, 2, 2, 4, 3],
                    [3, 3, 2, 2, 4, 3],
                ],
                'wild_value' => 3,
                'wild_ability' => 'shadow',
                'wild_ability_description' => 'Peek at upcoming cards (flavor ability)',
            ],
            [
                'name' => 'Godwin the Sage',
                'description' => 'A brilliant scholar whose knowledge spans every discipline from alchemy to astronomy. His analytical approach to problems and encyclopedic memory make him an invaluable advisor, though he sometimes overthinks when action is needed.',
                'dice' => [
                    [2, 2, 3, 3, 2, 'WILD'],
                    [2, 2, 3, 3, 2, 2],
                    [2, 2, 3, 3, 2, 2],
                ],
                'wild_value' => 5,
                'wild_ability' => 'wisdom',
                'wild_ability_description' => '+2 to the total roll',
            ],
        ];

        foreach ($characters as $character) {
            Character::create($character);
        }
    }
}
