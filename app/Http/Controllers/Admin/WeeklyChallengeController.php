<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WeeklyChallenge;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeeklyChallengeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(WeeklyChallenge::orderByDesc('week_start')->limit(30)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'week_start' => 'required|date|unique:weekly_challenges,week_start',
            'week_end' => 'required|date|after:week_start',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'criteria' => 'required|array',
            'criteria.type' => 'required|string',
            'reward_xp' => 'sometimes|integer|min:0',
            'reward_coins' => 'sometimes|integer|min:0',
            'is_manual' => 'boolean',
            'addon_id' => 'nullable|integer|exists:addons,id',
        ]);

        $validated['is_manual'] = true;
        $challenge = WeeklyChallenge::create($validated);
        return response()->json($challenge, 201);
    }

    public function show(WeeklyChallenge $weeklyChallenge): JsonResponse
    {
        return response()->json($weeklyChallenge);
    }

    public function update(Request $request, WeeklyChallenge $weeklyChallenge): JsonResponse
    {
        $validated = $request->validate([
            'week_start' => 'sometimes|date|unique:weekly_challenges,week_start,' . $weeklyChallenge->id,
            'week_end' => 'sometimes|date|after:week_start',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'criteria' => 'sometimes|array',
            'criteria.type' => 'required_with:criteria|string',
            'reward_xp' => 'sometimes|integer|min:0',
            'reward_coins' => 'sometimes|integer|min:0',
            'addon_id' => 'nullable|integer|exists:addons,id',
        ]);

        $weeklyChallenge->update($validated);
        return response()->json($weeklyChallenge);
    }

    public function destroy(WeeklyChallenge $weeklyChallenge): JsonResponse
    {
        $weeklyChallenge->delete();
        return response()->json(null, 204);
    }

    private array $templates = [
        ['title' => 'Seasoned Ruler', 'description' => 'Win 3 games this week.', 'criteria' => ['type' => 'win_games', 'count' => 3, 'mode' => 'any'], 'reward_xp' => 400, 'reward_coins' => 75],
        ['title' => 'Versatile Leader', 'description' => 'Play 5 games using different characters.', 'criteria' => ['type' => 'unique_characters_week', 'count' => 5], 'reward_xp' => 500, 'reward_coins' => 100],
        ['title' => 'Online Champion', 'description' => 'Win 2 online games.', 'criteria' => ['type' => 'win_games', 'count' => 2, 'mode' => 'online'], 'reward_xp' => 450, 'reward_coins' => 80],
        ['title' => 'Kingdom Builder', 'description' => 'Reach 18+ on any stat in 3 games.', 'criteria' => ['type' => 'stat_threshold_count', 'stat' => 'any', 'value' => 18, 'count' => 3], 'reward_xp' => 500, 'reward_coins' => 100],
        ['title' => 'Duel Master', 'description' => 'Win 3 duel games.', 'criteria' => ['type' => 'win_duel_games', 'count' => 3], 'reward_xp' => 450, 'reward_coins' => 80],
        ['title' => 'The Grind', 'description' => 'Complete 7 games this week.', 'criteria' => ['type' => 'play_games', 'count' => 7, 'mode' => 'any'], 'reward_xp' => 350, 'reward_coins' => 60],
    ];

    public function generateRange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $start = Carbon::parse($validated['start_date'])->startOfWeek(Carbon::MONDAY);
        $end = Carbon::parse($validated['end_date']);
        $created = 0;
        $skipped = 0;

        for ($weekStart = $start->copy(); $weekStart->lte($end); $weekStart->addWeek()) {
            $weekStartStr = $weekStart->toDateString();
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

            if (WeeklyChallenge::where('week_start', $weekStartStr)->exists()) {
                $skipped++;
                continue;
            }

            $index = $weekStart->weekOfYear % count($this->templates);
            $template = $this->templates[$index];

            WeeklyChallenge::create([
                'week_start' => $weekStartStr,
                'week_end' => $weekEnd->toDateString(),
                'title' => $template['title'],
                'description' => $template['description'],
                'criteria' => $template['criteria'],
                'reward_xp' => $template['reward_xp'],
                'reward_coins' => $template['reward_coins'],
                'is_manual' => false,
            ]);

            $created++;
        }

        return response()->json([
            'created' => $created,
            'skipped' => $skipped,
            'message' => "Generated {$created} weekly challenges, skipped {$skipped} existing.",
        ]);
    }
}
