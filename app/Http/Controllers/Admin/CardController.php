<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Traits\AuditsAdminActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardController extends Controller
{
    use AuditsAdminActions;
    public function index(): JsonResponse
    {
        return response()->json(
            Card::orderBy('sort_order')->get()
        );
    }

    public function show(Card $card): JsonResponse
    {
        return response()->json($card);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'question' => 'nullable|string|max:500',
            'sort_order' => 'required|integer',
            'difficulty' => 'required|integer|min:1|max:20',
            'positive_effects' => 'required|array',
            'negative_effects' => 'required|array',
            'positive_flavor' => 'nullable|string',
            'negative_flavor' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'available_cooperative' => 'boolean',
            'available_duel' => 'boolean',
            'difficulty_duel' => 'nullable|integer|min:1|max:20',
            'positive_effects_duel' => 'nullable|array',
            'negative_effects_duel' => 'nullable|array',
        ]);

        $card = Card::create($validated);
        $this->auditLog('create', 'Card', $card->id);

        return response()->json($card, 201);
    }

    public function update(Request $request, Card $card): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'question' => 'nullable|string|max:500',
            'sort_order' => 'required|integer',
            'difficulty' => 'required|integer|min:1|max:20',
            'positive_effects' => 'required|array',
            'negative_effects' => 'required|array',
            'positive_flavor' => 'nullable|string',
            'negative_flavor' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'available_cooperative' => 'boolean',
            'available_duel' => 'boolean',
            'difficulty_duel' => 'nullable|integer|min:1|max:20',
            'positive_effects_duel' => 'nullable|array',
            'negative_effects_duel' => 'nullable|array',
        ]);

        $old = $card->only(array_keys($validated));
        // Omitted nullable fields mean "cleared" on an admin edit; null them explicitly.
        $card->update([
            ...$validated,
            'question' => $validated['question'] ?? null,
            'positive_flavor' => $validated['positive_flavor'] ?? null,
            'negative_flavor' => $validated['negative_flavor'] ?? null,
            'category' => $validated['category'] ?? null,
            'difficulty_duel' => $validated['difficulty_duel'] ?? null,
            'positive_effects_duel' => $validated['positive_effects_duel'] ?? null,
            'negative_effects_duel' => $validated['negative_effects_duel'] ?? null,
        ]);
        $this->auditModelChange('update', $card, $old);

        return response()->json($card);
    }

    public function destroy(Card $card): JsonResponse
    {
        $this->auditLog('delete', 'Card', $card->id, null, ['title' => $card->title]);
        $card->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
