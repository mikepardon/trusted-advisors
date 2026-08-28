<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Unlockable;
use Illuminate\Database\Seeder;

class ItemShopSeeder extends Seeder
{
    public function run(): void
    {
        // Coin price by rarity tier.
        $priceByRarity = [
            'common' => 800,
            'rare' => 1800,
            'epic' => 3200,
            'legendary' => 6000,
        ];

        // Everything a player doesn't already own for free (i.e. non-starters) is buyable.
        $items = Item::query()->where('is_starter', false)->get();

        foreach ($items as $item) {
            Unlockable::updateOrCreate(
                ['type' => 'item', 'entity_id' => $item->id],
                [
                    'unlock_method' => 'coins',
                    'unlock_value' => (string) ($priceByRarity[$item->rarity] ?? 1000),
                ],
            );
        }
    }
}
