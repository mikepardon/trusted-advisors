<?php

namespace App\Console\Commands;

use App\Models\WeeklyChallenge;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateWeeklyChallenge extends Command
{
    protected $signature = 'app:generate-weekly-challenge {--date= : Specific date to generate for (defaults to current week)}';

    protected $description = 'Generate a weekly challenge if none exists for the current week';

    private array $templates = [
        ['title' => 'Seasoned Ruler', 'description' => 'Win 3 games this week.', 'criteria' => ['type' => 'win_games', 'count' => 3, 'mode' => 'any'], 'reward_xp' => 400, 'reward_coins' => 75],
        ['title' => 'Versatile Leader', 'description' => 'Play 5 games using different characters.', 'criteria' => ['type' => 'unique_characters_week', 'count' => 5], 'reward_xp' => 500, 'reward_coins' => 100],
        ['title' => 'Online Champion', 'description' => 'Win 2 online games.', 'criteria' => ['type' => 'win_games', 'count' => 2, 'mode' => 'online'], 'reward_xp' => 450, 'reward_coins' => 80],
        ['title' => 'Kingdom Builder', 'description' => 'Reach 18+ on any stat in 3 games.', 'criteria' => ['type' => 'stat_threshold_count', 'stat' => 'any', 'value' => 18, 'count' => 3], 'reward_xp' => 500, 'reward_coins' => 100],
        ['title' => 'Duel Master', 'description' => 'Win 3 duel games.', 'criteria' => ['type' => 'win_duel_games', 'count' => 3], 'reward_xp' => 450, 'reward_coins' => 80],
        ['title' => 'The Grind', 'description' => 'Complete 7 games this week.', 'criteria' => ['type' => 'play_games', 'count' => 7, 'mode' => 'any'], 'reward_xp' => 350, 'reward_coins' => 60],
    ];

    public function handle(): void
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::today();

        $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $date->copy()->endOfWeek(Carbon::SUNDAY);

        if (WeeklyChallenge::where('week_start', $weekStart->toDateString())->exists()) {
            $this->info("Weekly challenge already exists for week of {$weekStart->toDateString()}.");
            return;
        }

        $index = $weekStart->weekOfYear % count($this->templates);
        $template = $this->templates[$index];

        WeeklyChallenge::create([
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'title' => $template['title'],
            'description' => $template['description'],
            'criteria' => $template['criteria'],
            'reward_xp' => $template['reward_xp'],
            'reward_coins' => $template['reward_coins'],
            'is_manual' => false,
        ]);

        $this->info("Generated weekly challenge for {$weekStart->toDateString()}: {$template['title']}");
    }
}
