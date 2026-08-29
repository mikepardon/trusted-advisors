<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Character;
use App\Models\DailyChallenge;
use App\Models\Item;
use App\Services\ChallengeBalancer;
use App\Services\ChallengeBlurbGenerator;
use App\Services\SeededRng;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateDailyChallenge extends Command
{
    protected $signature = 'app:generate-daily-challenge
        {--date= : Specific date (YYYY-MM-DD), defaults to today}
        {--ahead= : Also ensure this many future days are generated (rolling window)}
        {--force : Overwrite an existing challenge for the date(s) instead of skipping}
        {--no-ai : Use the templated briefing instead of calling the AI (fast — for bulk/HTTP generation)}
        {--no-verify : Skip the winnability simulation/tuning (fast — for HTTP generation; verify later)}';

    protected $description = 'Generate a richly-customised daily challenge if none exists for the date';

    private const STATS = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];

    /**
     * The month cap for an endless-race daily. A run ends the moment the goal is met or a stat
     * collapses; this is only the safety limit (also a survival win). Kept modest so a real run
     * resolves in a sensible span and the winnability simulation stays cheap.
     */
    private const ROUND_CAP = 40;

    /**
     * Human-readable title per goal stat, used for the challenge title on single-stat runs.
     *
     * @var array<string, string>
     */
    private array $statTitles = [
        'wealth' => 'The Wealthy Reign',
        'influence' => 'Master of Influence',
        'security' => 'The Iron Fortress',
        'religion' => 'The Devout',
        'food' => 'The Bountiful',
        'happiness' => "The People's Champion",
    ];

    /**
     * Human labels for house-rule keys, fed to the blurb writer.
     *
     * @var array<string, string>
     */
    private array $houseRuleLabels = [
        'no_negative_effects' => 'no negative effects can harm the realm',
        'double_positive_effects' => 'every boon counts double',
        'hardcore_mode' => 'hardcore rules — any stat reaching 3 ends the reign',
        'draw_curse_per_round' => 'a fresh curse is drawn every month',
    ];

    public function handle(ChallengeBlurbGenerator $blurbGenerator, ChallengeBalancer $balancer): void
    {
        $force = (bool) $this->option('force');
        $noAi = (bool) $this->option('no-ai');
        $noVerify = (bool) $this->option('no-verify');

        // Rolling window: ensure today + the next N days all have a challenge.
        if ($this->option('ahead') !== null) {
            $ahead = (int) $this->option('ahead');
            for ($offset = 0; $offset <= $ahead; $offset++) {
                $this->generateFor(Carbon::today()->addDays($offset), $blurbGenerator, $balancer, $force, $noAi, $noVerify);
            }

            return;
        }

        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::today();

        $this->generateFor($date, $blurbGenerator, $balancer, $force, $noAi, $noVerify);
    }

    private function generateFor(Carbon $date, ChallengeBlurbGenerator $blurbGenerator, ChallengeBalancer $balancer, bool $force, bool $noAi = false, bool $noVerify = false): void
    {
        $dateStr = $date->toDateString();
        $existing = DailyChallenge::whereDate('date', $dateStr)->first();

        if ($existing && ! $force) {
            $this->info("Challenge already exists for {$dateStr} (use --force to regenerate).");

            return;
        }

        $rng = new SeededRng("dailygen:{$dateStr}");

        // Deterministic advisor assignment (locked characters allowed — it's just the daily).
        $characterIds = Character::orderBy('id')->pluck('id')->all();
        $seedCharacterId = $characterIds === [] ? null : $rng->pick($characterIds, 'character');
        $characterName = $seedCharacterId
            ? (string) (Character::whereKey($seedCharacterId)->value('name') ?? 'a nameless advisor')
            : 'a nameless advisor';

        [$goal, $goalText, $title] = $this->buildGoal($rng);
        $overrides = $this->buildStartOverrides($rng, $goal);
        $perStat = $overrides['per_stat'];
        [$houseRules, $hasCurse] = $this->buildHouseRules($rng);

        // Draw a fresh, varied loadout from every beneficial cooperative item (not just the
        // lone starter), so each day hands the player a different set.
        $itemIds = Item::where('available_cooperative', true)
            ->where('is_negative', false)
            ->orderBy('id')
            ->pluck('id')
            ->all();
        $loadoutSize = $rng->int(2, 3, 'loadoutsize');
        $seedLoadout = array_slice($rng->shuffle($itemIds, 'loadout'), 0, $loadoutSize);

        $loadoutNames = Item::whereIn('id', $seedLoadout)->orderBy('id')->pluck('name')->all();

        $rewardXp = 125 + (count($houseRules) * 25) + ($goal['type'] === 'stat_threshold' ? 0 : 25);
        $rewardCoins = 15 + (count($houseRules) * 10) + ($hasCurse ? 10 : 0);

        $criteria = [
            'mode' => 'cooperative',
            'rounds' => self::ROUND_CAP,
            'start' => ['all' => 8, 'per_stat' => $perStat],
            'goal' => $goal,
            'seed_character_id' => $seedCharacterId,
            'seed_loadout' => array_values($seedLoadout),
            'house_rules' => $houseRules,
            'reward_coins' => $rewardCoins,
        ];

        $blurbContext = [
            'character_name' => $characterName,
            'goal_text' => $goalText,
            'weak_stats' => $overrides['weak'],
            'strong_stats' => $overrides['strong'],
            'has_curse' => $hasCurse,
            'house_rules' => array_values(array_map(fn (string $key): string => $this->houseRuleLabels[$key] ?? $key, array_keys($houseRules))),
            'items' => $loadoutNames,
            'rounds' => self::ROUND_CAP,
        ];

        // --no-ai keeps HTTP-triggered bulk generation fast (no per-day Anthropic call,
        // which would blow the web request timeout across a range of dates).
        $description = $noAi
            ? $blurbGenerator->templatedFallback($blurbContext)
            : $blurbGenerator->generate($blurbContext);

        $data = [
            'title' => $title,
            'description' => $description,
            'criteria' => $criteria,
            'reward_xp' => $rewardXp,
            'is_manual' => false,
        ];

        if ($existing) {
            $existing->update($data);
            $this->verifyWinnable($existing, $balancer, $noVerify, $dateStr, $title, 'Regenerated');

            return;
        }

        $challenge = DailyChallenge::create([...$data, 'date' => $date]);
        $this->verifyWinnable($challenge, $balancer, $noVerify, $dateStr, $title, 'Generated');
    }

    /**
     * Tune the freshly-built challenge until a bot can actually win it (unless verification is
     * skipped for a fast HTTP run, in which case a later job/schedule verifies it).
     */
    private function verifyWinnable(DailyChallenge $challenge, ChallengeBalancer $balancer, bool $noVerify, string $dateStr, string $title, string $verb): void
    {
        if ($noVerify) {
            $this->info("{$verb} daily challenge for {$dateStr}: {$title} (winnability not yet verified).");

            return;
        }

        $result = $balancer->balance($challenge);
        $eased = $result['steps'] > 0 ? " after {$result['steps']} easing step(s)" : '';
        $this->info("{$verb} daily challenge for {$dateStr}: {$title} — {$result['success_rate']}% bot win rate{$eased}.");
    }

    /**
     * Deterministically pick a goal, its human phrasing, and a challenge title.
     *
     * @return array{0: array<string, mixed>, 1: string, 2: string}
     */
    private function buildGoal(SeededRng $rng): array
    {
        // Weighted toward the single-stat race, with occasional all-stat / floor goals.
        $type = $rng->pick(
            ['stat_threshold', 'stat_threshold', 'stat_threshold', 'stat_threshold_all', 'no_stat_below'],
            'goaltype',
        );

        return match ($type) {
            'stat_threshold_all' => (function () use ($rng): array {
                // Raising every stat at once is the hardest goal shape, so it targets a lower
                // band than a single-stat race. The balancer still verifies each run is winnable.
                $value = $rng->int(11, 12, 'allval');
                $targets = collect(self::STATS)->mapWithKeys(fn (string $stat): array => [$stat => $value])->all();

                return [
                    ['type' => 'stat_threshold_all', 'targets' => $targets],
                    "raise every stat to {$value}",
                    'The Balanced Crown',
                ];
            })(),
            'no_stat_below' => (function () use ($rng): array {
                $value = $rng->int(8, 10, 'floorval');

                return [
                    ['type' => 'no_stat_below', 'value' => $value],
                    "keep no stat below {$value}",
                    'Hold the Line',
                ];
            })(),
            default => (function () use ($rng): array {
                $stat = $rng->pick(self::STATS, 'goalstat');
                $value = $rng->int(13, 16, 'goalval');

                return [
                    ['type' => 'stat_threshold', 'stat' => $stat, 'value' => $value],
                    "reach {$value} ".ucfirst($stat),
                    $this->statTitles[$stat] ?? 'A Daily Trial',
                ];
            })(),
        };
    }

    /**
     * Deterministically weaken one or two stats (never the single-stat goal stat) so each
     * run has a distinct pressure point, and — roughly a third of the time — start one other
     * stat unusually strong for contrast. Both feed the briefing.
     *
     * @param  array<string, mixed>  $goal
     * @return array{per_stat: array<string, int>, weak: array<string, int>, strong: array<string, int>}
     */
    private function buildStartOverrides(SeededRng $rng, array $goal): array
    {
        $pool = self::STATS;
        if (($goal['type'] ?? null) === 'stat_threshold' && isset($goal['stat'])) {
            $pool = array_values(array_filter(self::STATS, fn (string $stat): bool => $stat !== $goal['stat']));
        }

        $ordered = $rng->shuffle($pool, 'startpick');
        $index = 0;

        $weak = [];
        $weakCount = $rng->int(1, 2, 'numweak');
        for (; $index < $weakCount && $index < count($ordered); $index++) {
            $stat = $ordered[$index];
            // A pressure point, not a death sentence — a weak stat starts low but recoverable.
            $weak[$stat] = $rng->int(5, 7, 'weakval', $stat);
        }

        $strong = [];
        if ($rng->int(0, 2, 'hasstrong') === 0 && $index < count($ordered)) {
            $stat = $ordered[$index];
            $strong[$stat] = $rng->int(11, 13, 'strongval', $stat);
        }

        return ['per_stat' => $weak + $strong, 'weak' => $weak, 'strong' => $strong];
    }

    /**
     * Deterministically enable 0–2 house rules. random_starting_stats is deliberately
     * excluded — it would overwrite the crafted per-stat weaknesses.
     *
     * @return array{0: array<string, bool>, 1: bool}
     */
    private function buildHouseRules(SeededRng $rng): array
    {
        $keys = ['no_negative_effects', 'double_positive_effects', 'hardcore_mode', 'draw_curse_per_round'];
        $count = $rng->pick([0, 0, 0, 1, 1, 2], 'numrules');
        $chosen = array_slice($rng->shuffle($keys, 'rulepick'), 0, $count);

        $houseRules = [];
        foreach ($chosen as $key) {
            $houseRules[$key] = true;
        }

        return [$houseRules, in_array('draw_curse_per_round', $chosen, true)];
    }
}
