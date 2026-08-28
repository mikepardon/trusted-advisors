<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyChallenge;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class DailyChallengeController extends Controller
{
    /**
     * Validation rules for the endless "race to a target" criteria shape. The run ends
     * the instant the goal stat is reached (a win) or a stat collapses (a loss). Shared
     * between store and update.
     *
     * @return array<string, mixed>
     */
    private function criteriaRules(): array
    {
        $stats = 'wealth,influence,security,religion,food,happiness';

        return [
            'criteria' => 'required|array',
            'criteria.mode' => 'required|string|in:cooperative',
            'criteria.rounds' => 'required|integer|min:1|max:120',
            'criteria.start' => 'required|array',
            'criteria.start.all' => 'required|integer|min:0|max:20',
            'criteria.start.per_stat' => 'nullable|array',
            'criteria.start.per_stat.*' => 'integer|min:0|max:20',

            // Goal: a single stat target, a per-stat target map, or a floor under every stat.
            'criteria.goal' => 'required|array',
            'criteria.goal.type' => 'required|string|in:stat_threshold,stat_threshold_all,no_stat_below',
            'criteria.goal.stat' => "required_if:criteria.goal.type,stat_threshold|string|in:{$stats}",
            'criteria.goal.value' => 'required_if:criteria.goal.type,stat_threshold,no_stat_below|integer|min:1|max:20',
            'criteria.goal.targets' => 'required_if:criteria.goal.type,stat_threshold_all|array',
            'criteria.goal.targets.*' => 'integer|min:1|max:20',

            'criteria.seed_character_id' => 'nullable|integer|exists:characters,id',
            'criteria.seed_loadout' => 'nullable|array|max:3',
            'criteria.seed_loadout.*' => 'integer|exists:items,id',

            // House rules — all seed-safe for a solo daily: the deterministic three, plus
            // random_starting_stats (seeded via rng) and draw_curse_per_round (draws from the
            // seeded curse deck by position, single player), so every player's run matches.
            'criteria.house_rules' => 'nullable|array',
            'criteria.house_rules.no_negative_effects' => 'boolean',
            'criteria.house_rules.double_positive_effects' => 'boolean',
            'criteria.house_rules.hardcore_mode' => 'boolean',
            'criteria.house_rules.random_starting_stats' => 'boolean',
            'criteria.house_rules.draw_curse_per_round' => 'boolean',

            // Content pools — restrict the deck for a themed run. Empty/absent = all content.
            'criteria.card_pool' => 'nullable|array',
            'criteria.card_pool.*' => 'integer|exists:cards,id',
            'criteria.item_pool' => 'nullable|array',
            'criteria.item_pool.*' => 'integer|exists:items,id',
            'criteria.event_pool' => 'nullable|array',
            'criteria.event_pool.*' => 'integer|exists:events,id',
            'criteria.curse_pool' => 'nullable|array',
            'criteria.curse_pool.*' => 'integer|exists:curses,id',

            'criteria.reward_coins' => 'nullable|integer|min:0|max:100000',
        ];
    }

    public function index(): JsonResponse
    {
        return response()->json(
            DailyChallenge::withCount('entries')->orderByDesc('date')->limit(60)->get(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date|unique:daily_challenges,date',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'reward_xp' => 'sometimes|integer|min:0',
            'addon_id' => 'nullable|integer|exists:addons,id',
            ...$this->criteriaRules(),
        ]);

        $validated['is_manual'] = true;
        $challenge = DailyChallenge::create($validated);

        return response()->json($challenge, 201);
    }

    public function show(DailyChallenge $dailyChallenge): JsonResponse
    {
        return response()->json($dailyChallenge);
    }

    public function update(Request $request, DailyChallenge $dailyChallenge): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'sometimes|date|unique:daily_challenges,date,' . $dailyChallenge->id,
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'reward_xp' => 'sometimes|integer|min:0',
            'addon_id' => 'nullable|integer|exists:addons,id',
            ...$this->criteriaRules(),
        ]);

        // Editing a challenge always marks it manual so the scheduler treats it as hand-tuned.
        // An omitted nullable addon_id means "Base Game" was selected; null it explicitly.
        $dailyChallenge->update([
            ...$validated,
            'is_manual' => true,
            'addon_id' => $validated['addon_id'] ?? null,
        ]);

        return response()->json($dailyChallenge);
    }

    public function destroy(DailyChallenge $dailyChallenge): JsonResponse
    {
        $dailyChallenge->delete();

        return response()->json(null, 204);
    }

    public function generateRange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);
        $created = 0;
        $skipped = 0;

        // Reuse the endless-shape generator (app:generate-daily-challenge) per date so the
        // admin range and the scheduled job produce identical, playable challenges.
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->toDateString();

            if (DailyChallenge::whereDate('date', $dateStr)->exists()) {
                $skipped++;
                continue;
            }

            Artisan::call('app:generate-daily-challenge', ['--date' => $dateStr]);

            if (DailyChallenge::whereDate('date', $dateStr)->exists()) {
                $created++;
            }
        }

        return response()->json([
            'created' => $created,
            'skipped' => $skipped,
            'message' => "Generated {$created} challenges, skipped {$skipped} existing.",
        ]);
    }
}
