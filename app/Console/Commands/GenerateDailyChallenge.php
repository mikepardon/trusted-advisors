<?php

namespace App\Console\Commands;

use App\Models\DailyChallenge;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateDailyChallenge extends Command
{
    protected $signature = 'app:generate-daily-challenge {--date= : Specific date (YYYY-MM-DD), defaults to today}';

    protected $description = 'Generate a daily challenge if none exists for the date';

    private array $templates = [
        [
            'title' => 'Play Any Game',
            'description' => 'Complete any game today.',
            'criteria' => ['type' => 'play_game', 'mode' => 'any'],
            'reward_xp' => 75,
        ],
        [
            'title' => 'Cooperative Victory',
            'description' => 'Win a cooperative game.',
            'criteria' => ['type' => 'win_game', 'mode' => 'any'],
            'reward_xp' => 100,
        ],
        [
            'title' => 'Wealthy Kingdom',
            'description' => 'Finish a game with Wealth at 18 or higher.',
            'criteria' => ['type' => 'stat_threshold', 'stat' => 'wealth', 'value' => 18],
            'reward_xp' => 150,
        ],
        [
            'title' => 'Influential Rule',
            'description' => 'Finish a game with Influence at 18 or higher.',
            'criteria' => ['type' => 'stat_threshold', 'stat' => 'influence', 'value' => 18],
            'reward_xp' => 150,
        ],
        [
            'title' => 'Fortress Kingdom',
            'description' => 'Finish a game with Security at 18 or higher.',
            'criteria' => ['type' => 'stat_threshold', 'stat' => 'security', 'value' => 18],
            'reward_xp' => 150,
        ],
        [
            'title' => 'Devout Realm',
            'description' => 'Finish a game with Religion at 18 or higher.',
            'criteria' => ['type' => 'stat_threshold', 'stat' => 'religion', 'value' => 18],
            'reward_xp' => 150,
        ],
        [
            'title' => 'Bountiful Harvest',
            'description' => 'Finish a game with Food at 18 or higher.',
            'criteria' => ['type' => 'stat_threshold', 'stat' => 'food', 'value' => 18],
            'reward_xp' => 150,
        ],
        [
            'title' => 'Joyful People',
            'description' => 'Finish a game with Happiness at 18 or higher.',
            'criteria' => ['type' => 'stat_threshold', 'stat' => 'happiness', 'value' => 18],
            'reward_xp' => 150,
        ],
        [
            'title' => 'Balanced Ruler',
            'description' => 'Finish a game with no stat below 8.',
            'criteria' => ['type' => 'no_stat_below', 'value' => 8],
            'reward_xp' => 200,
        ],
        [
            'title' => 'Online Warrior',
            'description' => 'Complete an online game.',
            'criteria' => ['type' => 'play_game', 'mode' => 'online'],
            'reward_xp' => 125,
        ],
    ];

    public function handle(): void
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::today();

        // Skip if a manual challenge already exists for this date
        $existing = DailyChallenge::where('date', $date->toDateString())->exists();
        if ($existing) {
            $this->info("Challenge already exists for {$date->toDateString()}.");
            return;
        }

        // Pick a template using the date as seed for deterministic rotation
        $index = $date->dayOfYear % count($this->templates);
        $template = $this->templates[$index];

        DailyChallenge::create([
            'date' => $date,
            'title' => $template['title'],
            'description' => $template['description'],
            'criteria' => $template['criteria'],
            'reward_xp' => $template['reward_xp'],
            'is_manual' => false,
        ]);

        $this->info("Generated daily challenge for {$date->toDateString()}: {$template['title']}");
    }
}
