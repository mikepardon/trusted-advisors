<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            CharacterSeeder::class,
            CardSeeder::class,
            EventSeeder::class,
            ItemSeeder::class,
            CurseSeeder::class,
            CosmeticSeeder::class,
            KingdomStyleSeeder::class,
            CharacterLevelOptionSeeder::class,
            AchievementSeeder::class,
            AppIconSeeder::class,
            SeasonPassSeeder::class,
            // Placeholder art references the content above, so it runs after it.
            PlaceholderImageSeeder::class,
            // Bot opponents — required for duel matchmaking's bot fallback.
            BotAccountSeeder::class,
        ]);
    }
}
