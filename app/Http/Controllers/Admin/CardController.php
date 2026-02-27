<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardController extends Controller
{
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
            'sort_order' => 'required|integer',
            'difficulty' => 'required|integer|min:1|max:20',
            'positive_effects' => 'required|array',
            'negative_effects' => 'required|array',
            'positive_flavor' => 'nullable|string',
            'negative_flavor' => 'nullable|string',
            'category' => 'nullable|string|max:100',
        ]);

        $card = Card::create($validated);

        return response()->json($card, 201);
    }

    public function update(Request $request, Card $card): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'sort_order' => 'required|integer',
            'difficulty' => 'required|integer|min:1|max:20',
            'positive_effects' => 'required|array',
            'negative_effects' => 'required|array',
            'positive_flavor' => 'nullable|string',
            'negative_flavor' => 'nullable|string',
            'category' => 'nullable|string|max:100',
        ]);

        $card->update($validated);

        return response()->json($card);
    }

    public function destroy(Card $card): JsonResponse
    {
        $card->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
