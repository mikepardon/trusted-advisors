<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyChallenge;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyChallengeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(DailyChallenge::orderByDesc('date')->limit(60)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date|unique:daily_challenges,date',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'criteria' => 'required|array',
            'criteria.type' => 'required|string',
            'reward_xp' => 'sometimes|integer|min:0',
            'is_manual' => 'boolean',
            'addon_id' => 'nullable|integer|exists:addons,id',
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
            'criteria' => 'sometimes|array',
            'criteria.type' => 'required_with:criteria|string',
            'reward_xp' => 'sometimes|integer|min:0',
            'addon_id' => 'nullable|integer|exists:addons,id',
        ]);

        $dailyChallenge->update($validated);
        return response()->json($dailyChallenge);
    }

    public function destroy(DailyChallenge $dailyChallenge): JsonResponse
    {
        $dailyChallenge->delete();
        return response()->json(null, 204);
    }

    private array $templates = [
        ['title' => 'Play Any Game', 'description' => 'Complete any game today.', 'criteria' => ['type' => 'play_game', 'mode' => 'any'], 'reward_xp' => 75],
        ['title' => 'Cooperative Victory', 'description' => 'Win a cooperative game.', 'criteria' => ['type' => 'win_game', 'mode' => 'any'], 'reward_xp' => 100],
        ['title' => 'Wealthy Kingdom', 'description' => 'Finish a game with Wealth at 18 or higher.', 'criteria' => ['type' => 'stat_threshold', 'stat' => 'wealth', 'value' => 18], 'reward_xp' => 150],
        ['title' => 'Influential Rule', 'description' => 'Finish a game with Influence at 18 or higher.', 'criteria' => ['type' => 'stat_threshold', 'stat' => 'influence', 'value' => 18], 'reward_xp' => 150],
        ['title' => 'Fortress Kingdom', 'description' => 'Finish a game with Security at 18 or higher.', 'criteria' => ['type' => 'stat_threshold', 'stat' => 'security', 'value' => 18], 'reward_xp' => 150],
        ['title' => 'Devout Realm', 'description' => 'Finish a game with Religion at 18 or higher.', 'criteria' => ['type' => 'stat_threshold', 'stat' => 'religion', 'value' => 18], 'reward_xp' => 150],
        ['title' => 'Bountiful Harvest', 'description' => 'Finish a game with Food at 18 or higher.', 'criteria' => ['type' => 'stat_threshold', 'stat' => 'food', 'value' => 18], 'reward_xp' => 150],
        ['title' => 'Joyful People', 'description' => 'Finish a game with Happiness at 18 or higher.', 'criteria' => ['type' => 'stat_threshold', 'stat' => 'happiness', 'value' => 18], 'reward_xp' => 150],
        ['title' => 'Balanced Ruler', 'description' => 'Finish a game with no stat below 8.', 'criteria' => ['type' => 'no_stat_below', 'value' => 8], 'reward_xp' => 200],
        ['title' => 'Online Warrior', 'description' => 'Complete an online game.', 'criteria' => ['type' => 'play_game', 'mode' => 'online'], 'reward_xp' => 125],
    ];

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

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->toDateString();

            if (DailyChallenge::where('date', $dateStr)->exists()) {
                $skipped++;
                continue;
            }

            $index = $date->dayOfYear % count($this->templates);
            $template = $this->templates[$index];

            DailyChallenge::create([
                'date' => $dateStr,
                'title' => $template['title'],
                'description' => $template['description'],
                'criteria' => $template['criteria'],
                'reward_xp' => $template['reward_xp'],
                'is_manual' => false,
            ]);

            $created++;
        }

        return response()->json([
            'created' => $created,
            'skipped' => $skipped,
            'message' => "Generated {$created} challenges, skipped {$skipped} existing.",
        ]);
    }
}
