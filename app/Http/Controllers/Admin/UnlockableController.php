<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Unlockable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnlockableController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Unlockable::orderBy('type')->orderBy('unlock_method')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:character,item',
            'entity_id' => 'required|integer',
            'unlock_method' => 'required|string|in:level,achievement',
            'unlock_value' => 'required|integer|min:1',
        ]);

        $unlockable = Unlockable::create($validated);
        return response()->json($unlockable, 201);
    }

    public function show(Unlockable $unlockable): JsonResponse
    {
        return response()->json($unlockable);
    }

    public function update(Request $request, Unlockable $unlockable): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'sometimes|string|in:character,item',
            'entity_id' => 'sometimes|integer',
            'unlock_method' => 'sometimes|string|in:level,achievement',
            'unlock_value' => 'sometimes|integer|min:1',
        ]);

        $unlockable->update($validated);
        return response()->json($unlockable);
    }

    public function destroy(Unlockable $unlockable): JsonResponse
    {
        $unlockable->delete();
        return response()->json(null, 204);
    }
}
