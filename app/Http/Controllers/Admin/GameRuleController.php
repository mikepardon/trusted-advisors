<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameRuleController extends Controller
{
    public function index(): JsonResponse
    {
        $rules = GameRule::all()->pluck('value', 'key');

        return response()->json($rules);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'value' => 'required',
        ]);

        $rule = GameRule::updateOrCreate(
            ['key' => $key],
            ['value' => $validated['value']]
        );

        return response()->json($rule);
    }
}
