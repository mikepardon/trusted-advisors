<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Season;
use App\Services\SeasonPassService;
use Illuminate\Database\Seeder;

class SeasonPassSeeder extends Seeder
{
    public function run(SeasonPassService $seasonPass): void
    {
        $season = Season::query()->active()->first();

        if ($season === null) {
            $season = Season::create([
                'name' => 'Season 1',
                'starts_at' => now(),
                'ends_at' => now()->addDays(30),
                'is_active' => true,
            ]);
        }

        $seasonPass->seedTiers($season);
    }
}
