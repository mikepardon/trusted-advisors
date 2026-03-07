<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RotatingEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RotatingEventController extends Controller
{
    public function index(): JsonResponse
    {
        $events = RotatingEvent::with('creator')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($events);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'image_url' => 'nullable|string|max:500',
            'game_type' => 'required|string|in:cooperative,duel',
            'game_mode' => 'required|string|in:single,pass_and_play,online',
            'modifiers' => 'nullable|array',
            'card_pool' => 'nullable|array',
            'card_pool.*' => 'integer|exists:cards,id',
            'item_pool' => 'nullable|array',
            'item_pool.*' => 'integer|exists:items,id',
            'event_pool' => 'nullable|array',
            'event_pool.*' => 'integer|exists:events,id',
            'character_pool' => 'nullable|array',
            'character_pool.*' => 'integer|exists:characters,id',
            'curse_pool' => 'nullable|array',
            'curse_pool.*' => 'integer|exists:curses,id',
            'fixed_event_id' => 'nullable|integer|exists:events,id',
            'total_rounds' => 'nullable|integer|in:12,24,36,48,60',
            'xp_config' => 'nullable|array',
            'affects_elo' => 'boolean',
            'theme_color' => 'nullable|string|max:7',
            'reward_coins' => 'integer|min:0',
            'max_attempts' => 'nullable|integer|min:1',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'is_active' => 'boolean',
            'visibility' => 'sometimes|string|in:all,premium,admin',
        ]);

        $validated['created_by'] = $request->user()->id;

        $event = RotatingEvent::create($validated);

        return response()->json($event, 201);
    }

    public function show(RotatingEvent $rotatingEvent): JsonResponse
    {
        $rotatingEvent->load('creator');
        $rotatingEvent->loadCount('entries');

        return response()->json($rotatingEvent);
    }

    public function update(Request $request, RotatingEvent $rotatingEvent): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'image_url' => 'nullable|string|max:500',
            'game_type' => 'sometimes|string|in:cooperative,duel',
            'game_mode' => 'sometimes|string|in:single,pass_and_play,online',
            'modifiers' => 'nullable|array',
            'card_pool' => 'nullable|array',
            'card_pool.*' => 'integer|exists:cards,id',
            'item_pool' => 'nullable|array',
            'item_pool.*' => 'integer|exists:items,id',
            'event_pool' => 'nullable|array',
            'event_pool.*' => 'integer|exists:events,id',
            'character_pool' => 'nullable|array',
            'character_pool.*' => 'integer|exists:characters,id',
            'curse_pool' => 'nullable|array',
            'curse_pool.*' => 'integer|exists:curses,id',
            'fixed_event_id' => 'nullable|integer|exists:events,id',
            'total_rounds' => 'nullable|integer|in:12,24,36,48,60',
            'xp_config' => 'nullable|array',
            'affects_elo' => 'sometimes|boolean',
            'theme_color' => 'nullable|string|max:7',
            'reward_coins' => 'sometimes|integer|min:0',
            'max_attempts' => 'nullable|integer|min:1',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'sometimes|date',
            'is_active' => 'sometimes|boolean',
            'visibility' => 'sometimes|string|in:all,premium,admin',
        ]);

        $rotatingEvent->update($validated);

        return response()->json($rotatingEvent);
    }

    public function destroy(RotatingEvent $rotatingEvent): JsonResponse
    {
        $rotatingEvent->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
