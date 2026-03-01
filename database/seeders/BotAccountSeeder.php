<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BotAccountSeeder extends Seeder
{
    public function run(): void
    {
        $usernames = [
            'DragonSlayer99', 'ShadowKnight', 'RuneKeeper', 'PhoenixRider', 'StormCaller',
            'IronWarden', 'FrostMage', 'CrimsonBlade', 'VoidWalker', 'ThunderHawk',
            'MysticSage', 'BlazeFury', 'NightProwler', 'SilverArrow', 'DarkRanger',
            'GoldenLion', 'ArcaneWitch', 'SteelFang', 'EmberKnight', 'WindRunner',
            'GhostBlade', 'SunWarrior', 'IceQueen', 'WolfHeart', 'StarForge',
            'DoomBringer', 'LightKeeper', 'StoneFist', 'RavenLord', 'FlameWarden',
            'TideWalker', 'SpellWeaver', 'IronClad', 'MoonShadow', 'StormBreaker',
            'CrystalMage', 'ViperStrike', 'ThornGuard', 'AshBringer', 'OakShield',
            'FrostBite', 'NovaSpark', 'DuskBlade', 'CoralReef', 'BearClaw',
            'HawkEye', 'PyroCaster', 'ZenMaster', 'NightOwl', 'IronHeart',
        ];

        foreach ($usernames as $i => $username) {
            // Skip if already exists
            if (User::where('email', "bot_{$i}@ta.local")->exists()) {
                continue;
            }

            // ELO weighted toward 900-1200 range
            $elo = $this->weightedElo();
            $level = rand(1, 15);
            $xp = User::xpForLevel($level) + rand(0, 99);

            User::create([
                'name' => $username,
                'email' => "bot_{$i}@ta.local",
                'password' => bcrypt(Str::random(32)),
                'is_bot' => true,
                'elo_rating' => $elo,
                'level' => $level,
                'xp' => $xp,
                'coins' => rand(50, 2000),
            ]);
        }
    }

    private function weightedElo(): int
    {
        // 60% chance: 900-1200 (core range)
        // 20% chance: 800-900 (low)
        // 15% chance: 1200-1400 (high)
        // 5% chance: 1400-1600 (very high)
        $roll = rand(1, 100);

        if ($roll <= 60) {
            return rand(900, 1200);
        } elseif ($roll <= 80) {
            return rand(800, 899);
        } elseif ($roll <= 95) {
            return rand(1200, 1400);
        } else {
            return rand(1400, 1600);
        }
    }
}
