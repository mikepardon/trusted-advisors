<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Event::orderBy('id')->get());
    }

    public function show(Event $event): JsonResponse
    {
        return response()->json($event);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'effect' => 'required|string',
            'stat_modifiers' => 'nullable|array',
            'addon_id' => 'nullable|integer|exists:addons,id',
            'mechanic' => 'nullable|string|in:stat_modifier,reduce_dice,grant_items,altered_deal',
            'mechanic_data' => 'nullable|array',
        ]);

        $event = Event::create($validated);

        return response()->json($event, 201);
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'effect' => 'required|string',
            'stat_modifiers' => 'nullable|array',
            'addon_id' => 'nullable|integer|exists:addons,id',
            'mechanic' => 'nullable|string|in:stat_modifier,reduce_dice,grant_items,altered_deal',
            'mechanic_data' => 'nullable|array',
        ]);

        $event->update($validated);

        return response()->json($event);
    }

    public function destroy(Event $event): JsonResponse
    {
        $event->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
