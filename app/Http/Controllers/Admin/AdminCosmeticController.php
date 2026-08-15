<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cosmetic;
use App\Traits\AuditsAdminActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCosmeticController extends Controller
{
    use AuditsAdminActions;

    private const RARITIES = ['common', 'rare', 'epic', 'legendary'];

    public function index(): JsonResponse
    {
        return response()->json(
            Cosmetic::orderBy('type')->orderBy('sort')->get(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(Cosmetic::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('cosmetics', 'slug')],
            'rarity' => ['required', 'string', Rule::in(self::RARITIES)],
            'value' => ['required', 'string', 'max:255'],
            'is_default' => ['boolean'],
            'is_available' => ['boolean'],
            'sort' => ['integer'],
            'meta' => ['nullable', 'array'],
        ]);

        $cosmetic = Cosmetic::create($this->attributes($validated));

        $this->auditLog('create', 'Cosmetic', $cosmetic->id);

        return response()->json($cosmetic, 201);
    }

    public function update(Request $request, Cosmetic $cosmetic): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(Cosmetic::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('cosmetics', 'slug')->ignore($cosmetic->id)],
            'rarity' => ['required', 'string', Rule::in(self::RARITIES)],
            'value' => ['required', 'string', 'max:255'],
            'is_default' => ['boolean'],
            'is_available' => ['boolean'],
            'sort' => ['integer'],
            'meta' => ['nullable', 'array'],
        ]);

        $old = $cosmetic->only(['type', 'name', 'slug', 'rarity', 'value', 'is_default', 'is_available', 'sort', 'meta']);

        $cosmetic->update($this->attributes($validated));

        $this->auditModelChange('update', $cosmetic, $old);

        return response()->json($cosmetic);
    }

    public function destroy(Cosmetic $cosmetic): JsonResponse
    {
        // Default cosmetics back the new-user defaults; removing one would break sign-up.
        if ($cosmetic->is_default) {
            return response()->json(
                ['message' => 'Default cosmetics cannot be deleted.'],
                422,
            );
        }

        $this->auditLog('delete', 'Cosmetic', $cosmetic->id, null, ['name' => $cosmetic->name]);

        $cosmetic->delete();

        return response()->json(null, 204);
    }

    /**
     * Extract only the named, validated attributes for persistence.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        return [
            'type' => $validated['type'],
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'rarity' => $validated['rarity'],
            'value' => $validated['value'],
            'is_default' => $validated['is_default'] ?? false,
            'is_available' => $validated['is_available'] ?? true,
            'sort' => $validated['sort'] ?? 0,
            'meta' => $validated['meta'] ?? null,
        ];
    }
}
