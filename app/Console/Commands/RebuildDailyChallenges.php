<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DailyChallenge;
use App\Services\ChallengeBalancer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RebuildDailyChallenges extends Command
{
    protected $signature = 'app:rebuild-daily-challenges
        {id? : A single challenge id to rebuild (defaults to today and future)}
        {--all : Rebuild every daily challenge, past included}
        {--upcoming : Rebuild today and all future-dated challenges (the default when no id is given)}';

    protected $description = 'Re-tune existing daily challenges so a bot can actually win them, and cache the win rate';

    public function handle(ChallengeBalancer $balancer): int
    {
        $challenges = $this->resolveChallenges();

        if ($challenges->isEmpty()) {
            $this->warn('No matching daily challenge to rebuild.');

            return self::FAILURE;
        }

        $fixed = 0;

        foreach ($challenges as $challenge) {
            $result = $balancer->balance($challenge);

            $status = $result['winnable'] ? 'winnable' : 'STILL HARD';
            $eased = $result['steps'] > 0 ? " (eased {$result['steps']} step(s))" : '';
            $this->line("#{$challenge->id} \"{$challenge->title}\" ({$challenge->date->toDateString()}) → {$result['success_rate']}% {$status}{$eased}");

            if ($result['steps'] > 0) {
                $fixed++;
            }
        }

        $this->info("Rebuilt {$challenges->count()} challenge(s); adjusted {$fixed} to be winnable.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, DailyChallenge>
     */
    private function resolveChallenges(): Collection
    {
        if ($this->argument('id') !== null) {
            return DailyChallenge::where('id', (int) $this->argument('id'))->get();
        }

        if ($this->option('all')) {
            return DailyChallenge::orderBy('date')->get();
        }

        return DailyChallenge::where('date', '>=', Carbon::today())->orderBy('date')->get();
    }
}
