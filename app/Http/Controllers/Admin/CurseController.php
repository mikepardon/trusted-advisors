<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curse;
use App\Traits\AuditsAdminActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CurseController extends Controller
{
    use AuditsAdminActions;

    public function index(): JsonResponse
    {
        return response()->json(Curse::orderBy('name')->get());
    }

    public function show(Curse $curse): JsonResponse
    {
        return response()->json($curse);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'negative_effect' => 'required|array',
            'positive_effect' => 'required|array',
            'negative_effect_duel' => 'nullable|array',
            'positive_effect_duel' => 'nullable|array',
            'is_available' => 'boolean',
        ]);

        $curse = Curse::create($validated);

        $this->auditLog('create', 'Curse', $curse->id);

        return response()->json($curse, 201);
    }

    public function update(Request $request, Curse $curse): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'negative_effect' => 'required|array',
            'positive_effect' => 'required|array',
            'negative_effect_duel' => 'nullable|array',
            'positive_effect_duel' => 'nullable|array',
            'is_available' => 'boolean',
        ]);

        $old = $curse->only(array_keys($validated));
        // Omitted nullable fields mean "cleared" on an admin edit; null them explicitly.
        $curse->update([
            ...$validated,
            'negative_effect_duel' => $validated['negative_effect_duel'] ?? null,
            'positive_effect_duel' => $validated['positive_effect_duel'] ?? null,
        ]);

        $this->auditModelChange('update', $curse, $old);

        return response()->json($curse);
    }

    public function destroy(Curse $curse): JsonResponse
    {
        $this->auditLog('delete', 'Curse', $curse->id, null, ['name' => $curse->name]);
        $curse->delete();

        return response()->json(null, 204);
    }

    public function uploadImage(Request $request, Curse $curse): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:2048',
        ]);

        if ($curse->image_path) {
            Storage::disk(config('filesystems.media_disk'))->delete($curse->image_path);
        }

        $path = $request->file('image')->store('curses', config('filesystems.media_disk'));
        $curse->update(['image_path' => $path]);

        return response()->json(['image_path' => $path]);
    }
}
