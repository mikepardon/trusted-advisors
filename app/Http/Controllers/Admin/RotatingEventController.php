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
            'reward_coins' => 'integer|min:0',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'is_active' => 'boolean',
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
            'reward_coins' => 'sometimes|integer|min:0',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'sometimes|date',
            'is_active' => 'sometimes|boolean',
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
