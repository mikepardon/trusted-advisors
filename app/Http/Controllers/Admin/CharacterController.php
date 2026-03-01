<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Unlockable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CharacterController extends Controller
{
    public function index(): JsonResponse
    {
        $characters = Character::orderBy('name')->get();

        // Attach unlockable info for each character
        $unlockables = Unlockable::where('type', 'character')
            ->get()
            ->groupBy('entity_id');

        $characters->each(function ($c) use ($unlockables) {
            $charUnlockables = $unlockables[$c->id] ?? collect();
            $c->unlock_info = $charUnlockables->map(fn ($u) => [
                'id' => $u->id,
                'method' => $u->unlock_method,
                'value' => $u->unlock_value,
            ])->values();
        });

        return response()->json($characters);
    }

    public function show(Character $character): JsonResponse
    {
        return response()->json($character);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'dice' => 'required|array|size:3',
            'dice.*' => 'required|array|size:6',
            'wild_value' => 'required|integer|min:1|max:10',
            'wild_ability' => 'required|string|max:50',
            'wild_ability_description' => 'nullable|string',
            'addon_id' => 'nullable|integer|exists:addons,id',
        ]);

        $character = Character::create($validated);

        return response()->json($character, 201);
    }

    public function update(Request $request, Character $character): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'dice' => 'required|array|size:3',
            'dice.*' => 'required|array|size:6',
            'wild_value' => 'required|integer|min:1|max:10',
            'wild_ability' => 'required|string|max:50',
            'wild_ability_description' => 'nullable|string',
            'addon_id' => 'nullable|integer|exists:addons,id',
        ]);

        $character->update($validated);

        return response()->json($character);
    }

    public function uploadImage(Request $request, Character $character): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|image|max:5120',
        ]);

        if ($character->image_path) {
            try {
                Storage::disk('s3')->delete($character->image_path);
            } catch (\Exception $e) {
                // ignore delete errors
            }
        }

        try {
            $path = $request->file('image')->store('characters', 's3');

            if (!$path) {
                return response()->json(['error' => 'Upload failed — check S3/Minio configuration'], 500);
            }

            $character->update(['image_path' => $path]);

            return response()->json($character->fresh());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Upload failed: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Character $character): JsonResponse
    {
        if ($character->image_path) {
            try {
                Storage::disk('s3')->delete($character->image_path);
            } catch (\Exception $e) {
                // ignore
            }
        }

        $character->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
