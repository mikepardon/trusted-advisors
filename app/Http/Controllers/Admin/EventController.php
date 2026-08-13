<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Traits\AuditsAdminActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    use AuditsAdminActions;
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
            'mechanic' => 'nullable|string|in:stat_modifier,reduce_dice,grant_items,altered_deal,score_event',
            'mechanic_data' => 'nullable|array',
            'stat_modifiers_duel' => 'nullable|array',
            'mechanic_duel' => 'nullable|string|in:stat_modifier,reduce_dice,grant_items,altered_deal,score_event',
            'mechanic_data_duel' => 'nullable|array',
            'available_cooperative' => 'boolean',
            'available_duel' => 'boolean',
        ]);

        $event = Event::create($validated);
        $this->auditLog('create', 'Event', $event->id);

        return response()->json($event, 201);
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'effect' => 'required|string',
            'stat_modifiers' => 'nullable|array',
            'addon_id' => 'nullable|integer|exists:addons,id',
            'mechanic' => 'nullable|string|in:stat_modifier,reduce_dice,grant_items,altered_deal,score_event',
            'mechanic_data' => 'nullable|array',
            'stat_modifiers_duel' => 'nullable|array',
            'mechanic_duel' => 'nullable|string|in:stat_modifier,reduce_dice,grant_items,altered_deal,score_event',
            'mechanic_data_duel' => 'nullable|array',
            'available_cooperative' => 'boolean',
            'available_duel' => 'boolean',
        ]);

        $old = $event->only(array_keys($validated));
        // Omitted nullable fields mean "cleared" on an admin edit; null them explicitly.
        $event->update([
            ...$validated,
            'stat_modifiers' => $validated['stat_modifiers'] ?? null,
            'addon_id' => $validated['addon_id'] ?? null,
            'mechanic' => $validated['mechanic'] ?? null,
            'mechanic_data' => $validated['mechanic_data'] ?? null,
            'stat_modifiers_duel' => $validated['stat_modifiers_duel'] ?? null,
            'mechanic_duel' => $validated['mechanic_duel'] ?? null,
            'mechanic_data_duel' => $validated['mechanic_data_duel'] ?? null,
        ]);
        $this->auditModelChange('update', $event, $old);

        return response()->json($event);
    }

    public function destroy(Event $event): JsonResponse
    {
        $this->auditLog('delete', 'Event', $event->id, null, ['title' => $event->title]);
        $event->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
