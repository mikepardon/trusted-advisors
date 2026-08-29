<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DailyChallenge;
use App\Services\ChallengeSimulator;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SimulateDailyChallenge extends Command
{
    protected $signature = 'app:simulate-daily-challenge
        {id? : The daily challenge id to simulate (defaults to today, or use --all/--upcoming)}
        {--runs=150 : Number of bot playthroughs per challenge}
        {--all : Simulate every daily challenge}
        {--upcoming : Simulate today and all future-dated challenges}';

    protected $description = 'Play a daily challenge many times with a bot to estimate its success rate and typical months-to-win';

    public function handle(ChallengeSimulator $simulator): int
    {
        $runs = max(1, (int) $this->option('runs'));
        $challenges = $this->resolveChallenges();

        if ($challenges->isEmpty()) {
            $this->warn('No matching daily challenge to simulate.');

            return self::FAILURE;
        }

        foreach ($challenges as $challenge) {
            $this->line("Simulating #{$challenge->id} \"{$challenge->title}\" ({$challenge->date->toDateString()}) — {$runs} runs…");

            $result = $simulator->simulate($challenge, $runs);

            $months = $result['avg_months'] === null ? 'n/a' : (string) $result['avg_months'];
            $this->info("  → {$result['success_rate']}% win rate ({$result['wins']}/{$result['runs']}), avg {$months} months to win.");
        }

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, DailyChallenge>
     */
    private function resolveChallenges(): \Illuminate\Support\Collection
    {
        if ($this->argument('id') !== null) {
            return DailyChallenge::where('id', (int) $this->argument('id'))->get();
        }

        if ($this->option('all')) {
            return DailyChallenge::orderBy('date')->get();
        }

        if ($this->option('upcoming')) {
            return DailyChallenge::where('date', '>=', Carbon::today())->orderBy('date')->get();
        }

        return DailyChallenge::where('date', Carbon::today())->get();
    }
}
