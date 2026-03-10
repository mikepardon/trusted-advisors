<?php

namespace Database\Seeders;

use App\Models\AppIcon;
use Illuminate\Database\Seeder;

class AppIconSeeder extends Seeder
{
    public function run(): void
    {
        $icons = [
            // Navigation
            ['key' => 'nav_shop', 'label' => 'Shop', 'category' => 'navigation', 'icon_type' => 'emoji', 'icon_value' => "\u{1F6D2}"],
            ['key' => 'nav_collection', 'label' => 'Collection', 'category' => 'navigation', 'icon_type' => 'emoji', 'icon_value' => "\u{1F0CF}"],
            ['key' => 'nav_campaigns', 'label' => 'Campaigns', 'category' => 'navigation', 'icon_type' => 'emoji', 'icon_value' => "\u{2694}"],
            ['key' => 'nav_friends', 'label' => 'Friends', 'category' => 'navigation', 'icon_type' => 'emoji', 'icon_value' => "\u{1F465}"],
            ['key' => 'nav_profile', 'label' => 'Profile', 'category' => 'navigation', 'icon_type' => 'emoji', 'icon_value' => "\u{1F464}"],

            // Stats
            ['key' => 'stat_wealth', 'label' => 'Wealth', 'category' => 'stats', 'icon_type' => 'emoji', 'icon_value' => "\u{1FA99}"],
            ['key' => 'stat_influence', 'label' => 'Influence', 'category' => 'stats', 'icon_type' => 'emoji', 'icon_value' => "\u{1F3DB}"],
            ['key' => 'stat_security', 'label' => 'Security', 'category' => 'stats', 'icon_type' => 'emoji', 'icon_value' => "\u{1F6E1}"],
            ['key' => 'stat_religion', 'label' => 'Religion', 'category' => 'stats', 'icon_type' => 'emoji', 'icon_value' => "\u{1F54C}"],
            ['key' => 'stat_food', 'label' => 'Food', 'category' => 'stats', 'icon_type' => 'emoji', 'icon_value' => "\u{1F33E}"],
            ['key' => 'stat_happiness', 'label' => 'Happiness', 'category' => 'stats', 'icon_type' => 'emoji', 'icon_value' => "\u{1F3AD}"],

            // UI
            ['key' => 'ui_coins', 'label' => 'Coins', 'category' => 'ui', 'icon_type' => 'emoji', 'icon_value' => "\u{1F9E9}"],
            ['key' => 'ui_elo_trophy', 'label' => 'ELO Trophy', 'category' => 'ui', 'icon_type' => 'emoji', 'icon_value' => "\u{1F3C6}"],
        ];

        foreach ($icons as $icon) {
            AppIcon::updateOrCreate(
                ['key' => $icon['key']],
                $icon
            );
        }
    }
}
