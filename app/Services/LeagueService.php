<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cosmetic;
use App\Models\LeagueCohort;
use App\Models\LeagueMember;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeagueService
{
    /** Ordered league divisions, lowest to highest. Index === users.league_tier. */
    public const TIERS = [
        ['name' => 'Wooden', 'color' => '#8a6a3a'],
        ['name' => 'Bronze', 'color' => '#c17a3a'],
        ['name' => 'Iron', 'color' => '#9aa3ad'],
        ['name' => 'Silver', 'color' => '#cbd5e1'],
        ['name' => 'Gold', 'color' => '#f2c14e'],
        ['name' => 'Sapphire', 'color' => '#3b82c4'],
        ['name' => 'Ruby', 'color' => '#c0392b'],
        ['name' => 'Obsidian', 'color' => '#4a4a55'],
        ['name' => 'Diamond', 'color' => '#6fd6e0'],
        ['name' => 'Royal', 'color' => '#b072e0'],
    ];

    private const COHORT_SIZE = 30;

    private const BOT_FILL_TARGET = 14;

    public const PROMOTE_COUNT = 3;

    public const DEMOTE_COUNT = 3;

    private const BOT_NAMES = [
        'Aldric', 'Beatrix', 'Cedric', 'Rowena', 'Godfrey', 'Isolde', 'Percival', 'Maud',
        'Tobias', 'Edwina', 'Barnaby', 'Cecily', 'Ewart', 'Millicent', 'Osric', 'Sibyl',
        'Reynard', 'Adela', 'Hugo', 'Winifred', 'Cuthbert', 'Emeline', 'Leofric', 'Wilhelmina',
    ];

    public function currentWeekStart(): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfWeek(CarbonInterface::MONDAY);
    }

    /**
     * Ensure the user is seated in a cohort for the current week, creating and
     * bot-filling a new cohort of their tier when needed.
     */
    public function ensureMembership(User $user): LeagueMember
    {
        $week = $this->currentWeekStart()->toDateString();

        // Serialise seating per user inside a transaction: two concurrent game
        // completions must not create the cohort/member rows twice (which could
        // otherwise seat the same user in two cohorts for the same week).
        return DB::transaction(function () use ($user, $week): LeagueMember {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $tier = $user->league_tier ?? 0;

            $existing = LeagueMember::query()
                ->where('user_id', $user->id)
                ->whereHas('cohort', fn ($query) => $query->whereDate('week_start', $week))
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            // Filter the count in PHP rather than via HAVING (portable across SQLite/Postgres).
            $cohort = LeagueCohort::query()
                ->where('tier', $tier)
                ->whereDate('week_start', $week)
                ->withCount('members')
                ->orderBy('id')
                ->get()
                ->first(fn (LeagueCohort $candidate): bool => $candidate->members_count < self::COHORT_SIZE);

            if ($cohort === null) {
                $cohort = LeagueCohort::create(['tier' => $tier, 'week_start' => $week]);
                $this->fillWithBots($cohort);
            }

            return LeagueMember::create([
                'cohort_id' => $cohort->id,
                'user_id' => $user->id,
                'score' => 0,
            ]);
        });
    }

    public function addScore(User $user, int $points): void
    {
        if ($points === 0) {
            return;
        }

        $member = $this->ensureMembership($user);

        // Losses subtract, so we can't use an atomic increment (it can't floor). Lock the
        // member row, apply the delta floored at 0 (the score column is unsigned), and save —
        // the lock still prevents a lost update between concurrent awards.
        DB::transaction(function () use ($member, $points): void {
            $locked = LeagueMember::query()->whereKey($member->id)->lockForUpdate()->first();
            $locked->score = max(0, $locked->score + $points);
            $locked->save();
        });
    }

    /**
     * The user's current cohort standings with promotion/demotion context.
     *
     * @return array<string, mixed>
     */
    public function standings(User $user): array
    {
        $member = $this->ensureMembership($user);
        $cohort = $member->cohort;
        $tier = $user->league_tier ?? 0;

        $members = LeagueMember::query()
            ->where('cohort_id', $cohort->id)
            ->with('user:id,name,crest_config')
            ->orderByDesc('score')
            ->orderBy('id')
            ->get();

        // Slug => numeric value maps so equipped crest parts resolve without N+1.
        $styleMap = Cosmetic::crestValueMap('crest_style');
        $colourMap = Cosmetic::crestValueMap('crest_colour');

        $rows = $members->values()->map(function (LeagueMember $entry, int $index) use ($user, $styleMap, $colourMap): array {
            $config = $entry->user?->crest_config;
            $styleSlug = data_get($config, 'crest_style');
            $colourSlug = data_get($config, 'crest_colour');

            return [
                'rank' => $index + 1,
                'name' => $entry->user_id === null ? $entry->bot_name : $entry->user?->name,
                'score' => $entry->score,
                'is_you' => $entry->user_id === $user->id,
                'is_bot' => $entry->user_id === null,
                // Player's equipped crest. Real players with no chosen style fall back
                // to 1 (their profile default); bots get a deterministic 1-5 for variety.
                'crest_style' => $styleMap[$styleSlug]
                    ?? ($entry->user_id === null ? ($entry->id % 5) + 1 : 1),
                'crest_colour' => $colourMap[$colourSlug] ?? 0,
            ];
        });

        $yourRank = $rows->firstWhere('is_you', true)['rank'] ?? null;

        return [
            'tier' => $tier,
            'tier_name' => self::TIERS[$tier]['name'],
            'tier_color' => self::TIERS[$tier]['color'],
            'week_start' => $cohort->week_start?->toDateString(),
            'total' => $rows->count(),
            'promote_count' => self::PROMOTE_COUNT,
            'demote_count' => self::DEMOTE_COUNT,
            'is_top_tier' => $tier >= count(self::TIERS) - 1,
            'is_bottom_tier' => $tier === 0,
            'your_rank' => $yourRank,
            'rows' => $rows->all(),
        ];
    }

    private function fillWithBots(LeagueCohort $cohort): void
    {
        /** @var Collection<int, string> $names */
        $names = collect(self::BOT_NAMES)->shuffle()->take(self::BOT_FILL_TARGET);

        foreach ($names as $name) {
            LeagueMember::create([
                'cohort_id' => $cohort->id,
                'user_id' => null,
                'bot_name' => $name,
                'score' => random_int(0, 320),
            ]);
        }
    }
}
