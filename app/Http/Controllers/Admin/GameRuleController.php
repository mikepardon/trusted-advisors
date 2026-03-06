<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

    public function removeHomepageBackground(): JsonResponse
    {
        $existing = GameRule::where('key', 'homepage_background_image')->first();
        if ($existing && $existing->value) {
            try {
                Storage::disk('s3')->delete($existing->value);
            } catch (\Exception $e) {
                // ignore delete errors
            }
            $existing->delete();
        }

        return response()->json(['message' => 'Background removed']);
    }

    public static function siteSettings(): JsonResponse
    {
        $bg = GameRule::where('key', 'homepage_background_image')->first();

        return response()->json([
            'homepage_background_url' => $bg && $bg->value ? '/api/storage/' . $bg->value : null,
        ]);
    }
}
